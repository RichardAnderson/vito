<?php

namespace App\Actions\Environment;

use App\Models\Application;
use App\Models\Database;
use App\Models\Service;

/**
 * The attach-a-resource dialog is reachable from both the resources page and the topology, so
 * the option lists it needs are built once here.
 */
class ListEnvironmentResourceOptions
{
    /**
     * @return array{servers: array<int, mixed>, databases: array<int, mixed>, redis: array<int, mixed>}
     */
    public function options(Application $application): array
    {
        $servers = $application->project->servers()->get(['id', 'name']);

        return [
            'servers' => $servers->all(),
            'databases' => Database::query()
                ->whereIn('server_id', $servers->pluck('id'))
                ->get(['id', 'name', 'server_id'])
                ->all(),
            'redis' => Service::query()
                ->where('type', 'memory_database')
                ->whereIn('server_id', $servers->pluck('id'))
                ->get(['id', 'name', 'version', 'server_id'])
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name.' '.$service->version,
                    'server_id' => $service->server_id,
                ])
                ->all(),
        ];
    }
}
