<?php

namespace App\Actions\EnvironmentDomain;

use App\Enums\SslStatus;
use App\Enums\SslType;
use App\Models\Domain;
use App\Models\EnvironmentDomain;
use App\Models\Server;
use App\Models\ServerLog;
use App\Models\Ssl;
use App\Models\UserProject;
use Exception;

/**
 * Creates the Ssl row an environment domain needs. Issuance itself is performed by the caller:
 * the nginx non-wildcard and custom paths run inline inside SyncEnvironmentDomainJob's lock,
 * while the wildcard path is chained after that lock is released.
 */
class IssueDomainCertificate
{
    public function nonWildcard(EnvironmentDomain $domain, Server $server): Ssl
    {
        $ssl = new Ssl([
            'server_id' => $server->id,
            'type' => $domain->ssl_method === 'custom' ? SslType::CUSTOM->value : SslType::LETSENCRYPT->value,
            'status' => SslStatus::CREATING,
            'domains' => [$domain->host],
            'email' => $this->resolveEmail($server),
        ]);
        $ssl->log_id = ServerLog::log($server, 'create-environment-domain-ssl', '')->id;
        $ssl->save();

        $domain->ssl_id = $ssl->id;
        $domain->save();

        return $ssl;
    }

    /**
     * @throws Exception
     */
    public function wildcardDomain(EnvironmentDomain $domain, Server $server): Domain
    {
        $zone = substr($domain->host, 2);

        /** @var ?Domain $zoneDomain */
        $zoneDomain = Domain::query()
            ->where('project_id', $server->project_id)
            ->where('domain', $zone)
            ->first();

        if ($zoneDomain === null) {
            throw new Exception('No DNS-connected domain found for '.$zone);
        }

        return $zoneDomain;
    }

    /**
     * @throws Exception
     */
    public function resolveEmail(Server $server): string
    {
        $existing = Ssl::query()
            ->where('server_id', $server->id)
            ->where('type', SslType::LETSENCRYPT)
            ->whereNotNull('email')
            ->first();

        if ($existing?->email) {
            return $existing->email;
        }

        /** @var ?UserProject $userProject */
        $userProject = $server->project->users()->with('user')->first();

        if ($userProject?->user?->email) {
            return $userProject->user->email;
        }

        throw new Exception('No email address available for Let\'s Encrypt certificate generation');
    }
}
