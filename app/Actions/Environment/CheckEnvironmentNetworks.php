<?php

namespace App\Actions\Environment;

use App\Models\Component;
use App\Models\Environment;
use App\Models\NetworkServer;

/**
 * Read-only. Components of one environment on different servers must reach each other over a
 * private network; this reports the servers that cannot.
 */
class CheckEnvironmentNetworks
{
    /**
     * @return array<int, array{server_id: int, server: string}>
     */
    public function unreachableServers(Environment $environment): array
    {
        $components = $environment->components()->with('server')->get();
        $serverIds = $components->pluck('server_id')->unique()->values();

        if ($serverIds->count() < 2) {
            return [];
        }

        $networksByServer = [];

        foreach ($serverIds as $serverId) {
            $networksByServer[$serverId] = NetworkServer::query()
                ->where('server_id', $serverId)
                ->pluck('network_id')
                ->all();
        }

        $stranded = [];

        foreach ($serverIds as $serverId) {
            $reachesAny = false;

            foreach ($serverIds as $otherId) {
                if ($otherId === $serverId) {
                    continue;
                }

                if (array_intersect($networksByServer[$serverId], $networksByServer[$otherId]) !== []) {
                    $reachesAny = true;
                    break;
                }
            }

            if (! $reachesAny) {
                /** @var ?Component $component */
                $component = $components->firstWhere('server_id', $serverId);

                $stranded[] = [
                    'server_id' => (int) $serverId,
                    'server' => (string) $component?->server->name,
                ];
            }
        }

        return $stranded;
    }
}
