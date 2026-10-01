<?php

namespace App\Actions\Environment;

use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Environment;
use App\Models\EnvironmentResource;
use App\Models\NetworkServer;
use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttachEnvironmentResource
{
    /**
     * Attachable resource kinds. Each maps a request-facing key to its model and, where the
     * model covers more than one kind of thing, the `services.type` that narrows it.
     */
    private const array TYPES = [
        'database' => ['model' => Database::class, 'service_type' => null],
        'database_user' => ['model' => DatabaseUser::class, 'service_type' => null],
        'redis' => ['model' => Service::class, 'service_type' => 'memory_database'],
    ];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function attach(Environment $environment, array $input): EnvironmentResource
    {
        Validator::make($input, [
            'resource_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'resource_id' => ['required', 'integer'],
            'alias' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $kind = self::TYPES[$input['resource_type']];
        $class = $kind['model'];

        $resource = $class::query()->find($input['resource_id']);

        if ($resource === null) {
            throw ValidationException::withMessages(['resource_id' => 'Resource not found.']);
        }

        if ($resource instanceof Service && $resource->type !== $kind['service_type']) {
            throw ValidationException::withMessages(['resource_id' => 'That service cannot be attached as a '.$input['resource_type'].'.']);
        }

        $this->assertSameProject($environment, $resource->server->project_id);
        $this->assertReachable($environment, $resource->server_id);

        $link = EnvironmentResource::query()->firstOrCreate([
            'environment_id' => $environment->id,
            'resource_type' => $class,
            'resource_id' => $resource->id,
        ]);

        if (($input['alias'] ?? null) !== null && $link->alias !== $input['alias']) {
            $link->alias = (string) $input['alias'];
            $link->save();
        }

        return $link;
    }

    /**
     * @throws ValidationException
     */
    private function assertSameProject(Environment $environment, int $resourceProjectId): void
    {
        if ($resourceProjectId !== $environment->application->project_id) {
            throw ValidationException::withMessages([
                'resource_id' => 'That resource belongs to another project.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertReachable(Environment $environment, int $resourceServerId): void
    {
        $componentServerIds = $environment->components()->pluck('server_id')->unique();

        if ($componentServerIds->isEmpty() || $componentServerIds->contains($resourceServerId)) {
            return;
        }

        $resourceNetworks = NetworkServer::query()->where('server_id', $resourceServerId)->pluck('network_id');

        $reachable = $componentServerIds->every(
            fn (int $serverId): bool => $serverId === $resourceServerId || NetworkServer::query()
                ->where('server_id', $serverId)
                ->whereIn('network_id', $resourceNetworks)
                ->exists()
        );

        if (! $reachable) {
            throw ValidationException::withMessages([
                'resource_id' => 'Some components in this environment share no private network with that resource.',
            ]);
        }
    }
}
