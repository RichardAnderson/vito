<?php

namespace App\Actions\EnvironmentDomain;

use App\Models\Domain;
use App\Models\Environment;
use App\Models\EnvironmentDomain;
use App\Models\HostedDomain;
use App\Models\Site;
use Illuminate\Validation\ValidationException;

/**
 * A hostname may be claimed once per terminating server, across every environment and against
 * Site domains. Domains and redirects share the check because they land on the same vhost.
 */
class AssertHostIsAvailable
{
    /**
     * @throws ValidationException
     */
    public function assert(Environment $environment, string $host, string $field, ?int $ignoreDomainId = null): void
    {
        $this->assertFreeOnServer($environment, $host, $field, $ignoreDomainId);
        $this->assertWildcardHasDnsProvider($environment, $host, $field);
    }

    /**
     * @throws ValidationException
     */
    private function assertFreeOnServer(
        Environment $environment,
        string $host,
        string $field,
        ?int $ignoreDomainId,
    ): void {
        $serverId = $environment->servingComponent()?->server_id;

        if ($serverId === null) {
            return;
        }

        // `sites.domain` and `hosted_domains.domain` are two unsynchronised copies of one fact,
        // so a host is only free on that server when neither claims it.
        $siteCollision = HostedDomain::query()
            ->where('domain', $host)
            ->whereHas('site', fn ($query) => $query->where('server_id', $serverId))
            ->exists()
            || Site::query()
                ->where('server_id', $serverId)
                ->where('domain', $host)
                ->exists();

        if ($siteCollision) {
            throw ValidationException::withMessages([
                $field => 'A site on that server already answers on this host.',
            ]);
        }

        $domainCollision = EnvironmentDomain::query()
            ->where('host', $host)
            ->where('environment_id', '!=', $environment->id)
            ->when($ignoreDomainId !== null, fn ($query) => $query->whereKeyNot($ignoreDomainId))
            ->get()
            ->contains(fn (EnvironmentDomain $domain): bool => $domain->terminatingComponent()?->server_id === $serverId);

        if ($domainCollision) {
            throw ValidationException::withMessages([
                $field => 'Another environment already answers on that host on the same server.',
            ]);
        }
    }

    /**
     * Both webservers reach a wildcard through certbot's DNS-01 challenge, so a wildcard without
     * a DNS provider is rejected at creation rather than failing during deployment.
     *
     * @throws ValidationException
     */
    private function assertWildcardHasDnsProvider(Environment $environment, string $host, string $field): void
    {
        if (! str_starts_with($host, '*.')) {
            return;
        }

        $zone = substr($host, 2);

        $domain = Domain::query()
            ->where('project_id', $environment->application->project_id)
            ->where('domain', $zone)
            ->first();

        if ($domain === null || ! $domain->dns_provider_id) {
            throw ValidationException::withMessages([
                $field => 'A wildcard host needs the domain "'.$zone.'" connected to a DNS provider in this project.',
            ]);
        }
    }
}
