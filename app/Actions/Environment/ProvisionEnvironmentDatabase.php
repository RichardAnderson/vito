<?php

namespace App\Actions\Environment;

use App\Actions\Database\CreateDatabase;
use App\Actions\Database\CreateDatabaseUser;
use App\Enums\DatabaseUserPermission;
use App\Models\Database;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attaches a database to an environment and records how to reach it as environment variables.
 *
 * The alias is the handle an application uses, so it owns a whole `DB_{ALIAS}_*` namespace and
 * must be free of any existing variable in that namespace.
 */
class ProvisionEnvironmentDatabase
{
    public const string ALIAS_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @param  array<string, mixed>  $input
     * @return array{database: Database, password: ?string, credentials_pending: bool, alias: ?string}
     *
     * @throws ValidationException
     */
    public function provision(Environment $environment, array $input): array
    {
        $source = $input['source'] ?? 'existing';

        return $source === 'new'
            ? $this->createAndAttach($environment, $input)
            : $this->attachExisting($environment, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{database: Database, password: ?string, credentials_pending: bool, alias: ?string}
     *
     * @throws ValidationException
     */
    private function createAndAttach(Environment $environment, array $input): array
    {
        $projectId = $environment->application->project_id;

        Validator::make($input, [
            'server_id' => ['required', Rule::exists('servers', 'id')->where('project_id', $projectId)],
            'name' => ['required', 'string', 'max:255'],
            'user' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:64', 'regex:'.self::ALIAS_PATTERN],
            'charset' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'collation' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._@-]+$/'],
        ])->validate();

        $alias = (string) $input['alias'];
        $this->assertAliasIsFree($environment, $alias);

        /** @var Server $server */
        $server = Server::query()->findOrFail($input['server_id']);

        $this->assertServerHasDatabase($server);

        [$charset, $collation] = $this->resolveCharset($server, $input);

        $password = Str::password(24, symbols: false);

        $database = app(CreateDatabase::class)->create($server, [
            'name' => $input['name'],
            'charset' => $charset,
            'collation' => $collation,
        ]);

        $databaseUser = app(CreateDatabaseUser::class)->create($server, [
            'username' => $input['user'],
            'password' => $password,
            'permission' => DatabaseUserPermission::ADMIN->value,
        ], [$database->name]);

        DB::transaction(function () use ($environment, $database, $databaseUser, $alias, $password): void {
            app(AttachEnvironmentResource::class)->attach($environment, [
                'resource_type' => 'database',
                'resource_id' => $database->id,
                'alias' => $alias,
            ]);
            app(AttachEnvironmentResource::class)->attach($environment, [
                'resource_type' => 'database_user',
                'resource_id' => $databaseUser->id,
                'alias' => $alias,
            ]);

            $this->writeVariable($environment, $alias, 'NAME', $database->name, false);
            $this->writeVariable($environment, $alias, 'USER', $databaseUser->username, false);
            $this->writeVariable($environment, $alias, 'PASSWORD', $password, true);
        });

        return ['database' => $database, 'password' => $password, 'credentials_pending' => false, 'alias' => $alias];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{database: Database, password: ?string, credentials_pending: bool, alias: ?string}
     *
     * @throws ValidationException
     */
    private function attachExisting(Environment $environment, array $input): array
    {
        $projectId = $environment->application->project_id;

        Validator::make($input, [
            'resource_id' => [
                'required',
                Rule::exists('databases', 'id')->whereIn(
                    'server_id',
                    Server::query()->where('project_id', $projectId)->pluck('id')
                ),
            ],
            'alias' => ['nullable', 'string', 'max:64', 'regex:'.self::ALIAS_PATTERN],
            'user' => ['nullable', 'string', 'max:255', 'required_with:password'],
            'password' => ['nullable', 'string', 'max:255', 'required_with:user'],
        ])->validate();

        $alias = $input['alias'] ?? null;

        if ($alias !== null && $alias !== '') {
            $this->assertAliasIsFree($environment, $alias);
        } else {
            $alias = null;
        }

        /** @var Database $database */
        $database = Database::query()->findOrFail($input['resource_id']);

        $wantsUser = ($input['user'] ?? '') !== '' && ($input['password'] ?? '') !== '';

        if ($wantsUser && $alias === null) {
            throw ValidationException::withMessages([
                'alias' => 'An alias is required to record credentials as variables.',
            ]);
        }

        $databaseUser = $wantsUser
            ? app(CreateDatabaseUser::class)->create($database->server, [
                'username' => $input['user'],
                'password' => $input['password'],
                'permission' => DatabaseUserPermission::ADMIN->value,
            ], [$database->name])
            : null;

        DB::transaction(function () use ($environment, $database, $databaseUser, $alias, $input): void {
            app(AttachEnvironmentResource::class)->attach($environment, [
                'resource_type' => 'database',
                'resource_id' => $database->id,
                'alias' => $alias,
            ]);

            if ($databaseUser !== null) {
                app(AttachEnvironmentResource::class)->attach($environment, [
                    'resource_type' => 'database_user',
                    'resource_id' => $databaseUser->id,
                    'alias' => $alias,
                ]);
            }

            if ($alias === null) {
                return;
            }

            $this->writeVariable($environment, $alias, 'NAME', $database->name, false);

            if ($databaseUser !== null) {
                $this->writeVariable($environment, $alias, 'USER', $databaseUser->username, false);
                $this->writeVariable($environment, $alias, 'PASSWORD', (string) $input['password'], true);
            }
        });

        return [
            'database' => $database,
            'password' => null,
            'credentials_pending' => $alias !== null && $databaseUser === null,
            'alias' => $alias,
        ];
    }

    private function writeVariable(Environment $environment, string $alias, string $suffix, string $value, bool $isSecret): void
    {
        app(UpsertEnvironmentVariable::class)->upsert($environment, [
            'key' => self::key($alias, $suffix),
            'value' => $value,
            'is_secret' => $isSecret,
        ]);
    }

    public static function key(string $alias, string $suffix): string
    {
        return 'DB_'.$alias.'_'.$suffix;
    }

    /**
     * @throws ValidationException
     */
    private function assertAliasIsFree(Environment $environment, string $alias): void
    {
        $taken = $environment->variables()
            ->whereNull('component_id')
            ->where('key', 'like', 'DB_'.$alias.'\_%')
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'alias' => 'Another database in this environment already uses the alias '.$alias.'.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertServerHasDatabase(Server $server): void
    {
        if (! $server->database() instanceof Service) {
            throw ValidationException::withMessages([
                'server_id' => 'That server has no database service installed.',
            ]);
        }
    }

    /**
     * `type_data['charsets']` maps a charset to `{default, list}`, so a collation is never the
     * charset's own name — reading it as a flat list produced an invalid collation on Postgres.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string}
     *
     * @throws ValidationException
     */
    private function resolveCharset(Server $server, array $input): array
    {
        /** @var Service $service */
        $service = $server->database();
        $charsets = $service->type_data['charsets'] ?? [];

        $charset = (string) ($input['charset'] ?? $service->type_data['defaultCharset'] ?? array_key_first($charsets));

        if (! array_key_exists($charset, $charsets)) {
            throw ValidationException::withMessages([
                'charset' => 'That charset is not available on this database service.',
            ]);
        }

        $list = (array) data_get($charsets, $charset.'.list', []);
        $collation = (string) ($input['collation'] ?? data_get($charsets, $charset.'.default') ?? ($list[0] ?? ''));

        if ($collation === '' || ($list !== [] && ! in_array($collation, $list, true))) {
            throw ValidationException::withMessages([
                'collation' => 'That collation is not available for the '.$charset.' charset.',
            ]);
        }

        return [$charset, $collation];
    }
}
