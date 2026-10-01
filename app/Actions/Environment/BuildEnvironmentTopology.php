<?php

namespace App\Actions\Environment;

use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\ComponentFunction;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Environment;
use App\Models\EnvironmentResource;
use App\Models\NetworkServer;
use App\Enums\EnvironmentDomainType;
use App\Models\EnvironmentDomain;
use App\Models\Service;

/**
 * The canvas prop. Tiers order the columns left to right; when the load balancer is a function
 * on an http component rather than a dedicated one, that server becomes the leading column so
 * the graph still reads root-input-left, backends-right.
 */
class BuildEnvironmentTopology
{
    /**
     * @return array<string, mixed>
     */
    public function build(Environment $environment): array
    {
        $components = $environment->components()->with(['server', 'functions', 'backends'])->get();
        $domains = $environment->domains()->get();
        $aliases = $domains->where('type', EnvironmentDomainType::ALIAS);
        $servingComponentId = $environment->servingComponent()?->id;

        $backendComponentIds = $components
            ->flatMap(fn (Component $component): array => $component->backends->pluck('target_component_id')->all())
            ->unique();

        $networkIds = [];
        foreach ($components->pluck('server_id')->unique() as $serverId) {
            $networkIds[$serverId] = NetworkServer::query()->where('server_id', $serverId)->pluck('network_id')->all();
        }

        $shared = $this->sharedNetwork($networkIds);

        return [
            'networked' => $shared !== null,
            'network_name' => $shared,
            'servers' => $components
                ->unique('server_id')
                ->map(fn (Component $component): array => [
                    'id' => $component->server_id,
                    'name' => $component->server->name,
                    'tier' => $this->tier($component, $backendComponentIds),
                    'ip' => $component->server->ip,
                    'private_ip' => NetworkServer::query()->where('server_id', $component->server_id)->value('ip'),
                ])
                ->values()
                ->all(),
            'components' => $components
                ->map(fn (Component $component): array => [
                    'id' => $component->id,
                    'name' => $component->name,
                    'type' => $component->type->getText(),
                    'status' => $component->status->getText(),
                    'status_color' => $component->status->getColor(),
                    'functions' => $component->functions->map(fn (ComponentFunction $f): string => $f->function)->all(),
                    'server_id' => $component->server_id,
                    'needs_redeploy' => $component->needs_redeploy,
                ])
                ->values()
                ->all(),
            'domains' => $aliases
                ->map(fn (EnvironmentDomain $domain): array => [
                    'id' => $domain->id,
                    'host' => $domain->host,
                    'component_id' => $servingComponentId,
                    'ssl' => $domain->ssl_method !== 'none',
                    'status' => $domain->status->getText(),
                    'redirects' => $domains
                        ->where('type', EnvironmentDomainType::REDIRECT)
                        ->where('redirect_to_domain_id', $domain->id)
                        ->map(fn (EnvironmentDomain $redirect): string => $redirect->host)
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'resources' => $environment->resources()->with('resource')->get()
                ->map(fn (EnvironmentResource $row): array => [
                    'id' => $row->id,
                    'name' => $this->resourceName($row),
                    'type' => $this->resourceType($row),
                    'engine' => $this->resourceEngine($row),
                    'alias' => $row->alias,
                    'server_id' => $row->resource instanceof Database
                        || $row->resource instanceof DatabaseUser
                        || $row->resource instanceof Service
                        ? $row->resource->server_id
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $backendComponentIds
     */
    private function tier(Component $component, $backendComponentIds): int
    {
        if ($component->type === ComponentType::LOAD_BALANCER || $component->type === ComponentType::PROXY) {
            return 0;
        }

        // An http component that something load-balances to sits behind the edge.
        if ($backendComponentIds->contains($component->id)) {
            return 1;
        }

        return $component->terminatesHttp() ? 0 : 1;
    }

    /**
     * @param  array<int, array<int, int>>  $networkIds
     */
    private function sharedNetwork(array $networkIds): ?string
    {
        if (count($networkIds) < 2) {
            return null;
        }

        $intersection = array_intersect(...array_values($networkIds));

        if ($intersection === []) {
            return null;
        }

        return \App\Models\Network::query()->whereKey(reset($intersection))->value('name');
    }

    private function resourceName(EnvironmentResource $row): string
    {
        $resource = $row->resource;

        return match (true) {
            $resource instanceof Database => $resource->name,
            $resource instanceof DatabaseUser => $resource->username,
            $resource instanceof Service => $resource->name,
            default => '#'.$row->resource_id,
        };
    }

    /**
     * The human name for what the resource is, rather than the class backing it — a Service row
     * of type `memory_database` reads as "in-memory database", not "Service".
     */
    /**
     * What actually runs the resource — the database service's name for a Database, the service's
     * own name for anything Service-backed.
     */
    private function resourceEngine(EnvironmentResource $row): ?string
    {
        $resource = $row->resource;

        return match (true) {
            $resource instanceof Database, $resource instanceof DatabaseUser => $resource->server->database()?->name,
            $resource instanceof Service => $resource->name,
            default => null,
        };
    }

    private function resourceType(EnvironmentResource $row): string
    {
        $resource = $row->resource;

        return match (true) {
            $resource instanceof Database => 'database',
            $resource instanceof DatabaseUser => 'database user',
            $resource instanceof Service => str_replace('_', '-', $resource->type),
            default => class_basename($row->resource_type),
        };
    }
}
