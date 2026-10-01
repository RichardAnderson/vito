<?php

namespace App\Actions\Component;

use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Enums\ServerStatus;
use App\Jobs\Component\InstallComponentJob;
use App\Models\Component;
use App\Models\Environment;
use App\Models\IsolatedUser;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateComponent
{
    public const ISOLATED_USER_PATTERN = '/^[a-z_][a-z0-9_-]*[a-z0-9]$/';

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(Environment $environment, array $input): Component
    {
        $this->validate($environment, $input);

        /** @var Server $server */
        $server = Server::query()->findOrFail($input['server_id']);
        $type = ComponentType::from($input['type']);
        $user = (string) $input['user'];

        $component = DB::transaction(function () use ($environment, $server, $type, $user, $input): Component {
            $isolatedUser = $user === $server->getSshUser()
                ? null
                : IsolatedUser::query()->firstOrCreate([
                    'server_id' => $server->id,
                    'username' => $user,
                ]);

            $component = new Component([
                'environment_id' => $environment->id,
                'server_id' => $server->id,
                'isolated_user_id' => $isolatedUser?->id,
                'name' => $input['name'],
                'type' => $type,
                'web_directory' => $input['web_directory'] ?? '/public',
                'php_version' => $input['php_version'] ?? null,
                'type_data' => $input['type_data'] ?? null,
                'status' => ComponentStatus::CREATING,
            ]);
            $component->save();

            $component->setRelation('environment', $environment);
            $component->setRelation('isolatedUser', $isolatedUser);
            $component->path = $component->basePath();
            $component->save();

            if ($type === ComponentType::APP) {
                foreach ($input['functions'] ?? [] as $function) {
                    $component->functions()->create([
                        'function' => $function,
                        'status' => ComponentStatus::CREATING,
                    ]);
                }
            }

            if ($environment->primary_component_id === null && $type === ComponentType::APP) {
                $environment->primary_component_id = $component->id;
                $environment->save();
            }

            return $component;
        });

        DB::afterCommit(fn () => dispatch(new InstallComponentJob($component))->onQueue('ssh'));

        return $component;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Environment $environment, array $input): void
    {
        $projectId = $environment->application->project_id;

        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('components', 'name')->where('environment_id', $environment->id),
            ],
            'server_id' => [
                'required',
                Rule::exists('servers', 'id')->where('project_id', $projectId),
            ],
            'type' => ['required', Rule::enum(ComponentType::class)],
            'functions' => ['array'],
            'functions.*' => ['string', Rule::in(ComponentFunctions::ids())],
            'user' => [
                'required',
                'regex:'.self::ISOLATED_USER_PATTERN,
                'min:3',
                'max:32',
                Rule::notIn(config('core.reserved_user_names')),
            ],
            'php_version' => ['nullable', 'string', 'max:8'],
            'web_directory' => ['nullable', 'string', 'max:255'],
        ])->validate();

        /** @var Server $server */
        $server = Server::query()->findOrFail($input['server_id']);
        $type = ComponentType::from($input['type']);
        $functions = $type === ComponentType::APP ? ($input['functions'] ?? []) : [];

        $this->assertServerIsReady($server);
        $this->assertTypeIsSupported($type);
        $this->assertFunctionsArePresent($type, $functions);
        $this->assertFunctionsAreSupported($server, $functions);
        $this->assertPhpVersionIsInstalled($server, $input['php_version'] ?? null);
        $this->assertSingleScheduler($environment, $functions, null);
        $this->assertPlacementIsFree($environment, $server, (string) $input['user'], null);
    }

    /**
     * @throws ValidationException
     */
    private function assertServerIsReady(Server $server): void
    {
        if ($server->status !== ServerStatus::READY) {
            throw ValidationException::withMessages([
                'server_id' => 'The server is not ready.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertTypeIsSupported(ComponentType $type): void
    {
        if (in_array($type, [ComponentType::CONTAINER, ComponentType::PROXY], true)) {
            throw ValidationException::withMessages([
                'type' => 'This component type is not yet supported.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $functions
     *
     * @throws ValidationException
     */
    private function assertFunctionsArePresent(ComponentType $type, array $functions): void
    {
        if ($type === ComponentType::APP && $functions === []) {
            throw ValidationException::withMessages([
                'functions' => 'Select at least one function for an app component.',
            ]);
        }
    }

    /**
     * Capability is gated per function, not per component: a queue-only component on a server
     * with no webserver is valid.
     *
     * @param  array<int, string>  $functions
     *
     * @throws ValidationException
     */
    private function assertFunctionsAreSupported(Server $server, array $functions): void
    {
        foreach ($functions as $function) {
            if ($function === ComponentFunctions::HTTP && $server->webserver() === null) {
                throw ValidationException::withMessages([
                    'functions' => 'The server has no webserver installed.',
                ]);
            }

            if ($function === ComponentFunctions::PROCESSES && $server->processManager() === null) {
                throw ValidationException::withMessages([
                    'functions' => 'The server has no process manager installed.',
                ]);
            }
        }
    }

    /**
     * A component whose PHP version is not installed would fail at install time with an SSH
     * error rather than a 422, so it is gated here alongside the per-function capability checks.
     *
     * @throws ValidationException
     */
    private function assertPhpVersionIsInstalled(Server $server, ?string $phpVersion): void
    {
        if ($phpVersion === null || $phpVersion === '') {
            return;
        }

        if ($server->php($phpVersion) === null) {
            throw ValidationException::withMessages([
                'php_version' => 'PHP '.$phpVersion.' is not installed on that server.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $functions
     *
     * @throws ValidationException
     */
    private function assertSingleScheduler(Environment $environment, array $functions, ?int $ignoreComponentId): void
    {
        if (! in_array(ComponentFunctions::SCHEDULER, $functions, true)) {
            return;
        }

        $exists = $environment->components()
            ->when($ignoreComponentId !== null, fn ($query) => $query->whereKeyNot($ignoreComponentId))
            ->whereHas('functions', fn ($query) => $query->where('function', ComponentFunctions::SCHEDULER))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'functions' => 'Another component in this environment already runs the scheduler.',
            ]);
        }
    }

    /**
     * `basePath()` carries no component identity, so two components sharing a (server, user) would
     * share one checkout and one `current` symlink — and per-component release pruning would delete
     * the sibling's active release. That topology is one component with both functions instead.
     *
     * @throws ValidationException
     */
    private function assertPlacementIsFree(Environment $environment, Server $server, string $user, ?int $ignoreComponentId): void
    {
        $taken = $environment->components()
            ->where('server_id', $server->id)
            ->when($ignoreComponentId !== null, fn ($query) => $query->whereKeyNot($ignoreComponentId))
            ->get()
            ->contains(fn (Component $component): bool => $component->user === $user);

        if ($taken) {
            throw ValidationException::withMessages([
                'server_id' => 'This environment already places a component on that server and user. Add the functions to that component instead.',
            ]);
        }
    }
}
