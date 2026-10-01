<?php

namespace App\Jobs\EnvironmentDomain;

use App\Actions\Component\SyncComponentVhost;
use App\Actions\EnvironmentDomain\AssertHostIsAvailable;
use App\Actions\EnvironmentDomain\IssueDomainCertificate;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\SslStatus;
use App\Exceptions\SSHError;
use App\Jobs\SSL\CreateLetsEncryptWildcardSslJob;
use App\Models\Component;
use App\Models\EnvironmentDomain;
use App\Models\Server;
use App\Models\ServerLog;
use App\Models\Ssl;
use App\Services\Webserver\Caddy;
use App\Services\Webserver\Webserver;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The two webservers issue certificates by fundamentally different mechanisms, so this branches
 * on the handler rather than on a type signature.
 *
 * Nginx needs three ordered operations because `certbot --nginx` requires an existing
 * server_name block. Caddy auto-issues from the site address and needs no Ssl row at all —
 * except for a wildcard, which its stock build cannot obtain, so that goes through certbot.
 */
class SyncEnvironmentDomainJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    /** @var array{server: Server, ssl: Ssl}|null */
    private ?array $chainAfterRun = null;

    public function __construct(protected EnvironmentDomain $domain) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $component = $this->domain->terminatingComponent();

        // A domain may be registered before the environment has anywhere to serve it. It parks
        // as PENDING and is synced by InstallComponentJob once a component can answer for it.
        if ($component === null) {
            $this->markStatus(EnvironmentDomainStatus::PENDING);

            return;
        }

        // A domain registered before the environment had a server skipped the collision check at
        // creation, so it is re-checked here now that a terminating server is known.
        try {
            app(AssertHostIsAvailable::class)->assert(
                $this->domain->environment,
                $this->domain->host,
                'host',
                $this->domain->id,
            );
        } catch (ValidationException) {
            $this->markStatus(EnvironmentDomainStatus::FAILED);

            return;
        }

        $this->run("server-{$component->server_id}", function () use ($component): void {
            $this->isCaddy($component)
                ? $this->syncCaddy($component)
                : $this->syncNginx($component);
        });

        // Wildcard issuance is asynchronous and its job takes the same server lock, so the chain
        // is only dispatched once run() has released it.
        if ($this->chainAfterRun !== null) {
            Bus::chain([
                new CreateLetsEncryptWildcardSslJob($this->chainAfterRun['server'], $this->chainAfterRun['ssl']),
                new FinaliseDomainCertificateJob($this->domain, $this->chainAfterRun['server']->id),
            ])->onQueue('ssh')->dispatch();
        }
    }

    public function failed(Throwable $e): void
    {
        $this->markStatus(EnvironmentDomainStatus::FAILED);
    }

    private function isCaddy(Component $component): bool
    {
        return $component->server->webserver()?->name === Caddy::id();
    }

    /**
     * @throws SSHError
     */
    private function syncNginx(Component $component): void
    {
        if ($this->domain->ssl_method === 'none') {
            app(SyncComponentVhost::class)->sync($component);
            $this->markStatus(EnvironmentDomainStatus::ACTIVE);

            return;
        }

        // Operation 1: an HTTP-only vhost, because certbot --nginx needs a server_name block.
        app(SyncComponentVhost::class)->sync($component, httpOnly: true);

        if ($this->domain->isWildcard()) {
            $this->prepareWildcardChain($component);

            return;
        }

        // Operation 2 and 3 run inline: this path reaches CREATED within the same lock.
        $ssl = app(IssueDomainCertificate::class)->nonWildcard($this->domain, $component->server);

        /** @var Webserver $webserver */
        $webserver = $component->server->webserver()?->handler();
        $webserver->setupSSL($ssl);

        app(SyncComponentVhost::class)->sync($component->fresh() ?? $component);
        $this->markStatus(EnvironmentDomainStatus::ACTIVE);
    }

    /**
     * @throws SSHError
     */
    private function syncCaddy(Component $component): void
    {
        if ($this->domain->ssl_method === 'letsencrypt' && $this->domain->isWildcard()) {
            app(SyncComponentVhost::class)->sync($component);
            $this->prepareWildcardChain($component);

            return;
        }

        if ($this->domain->ssl_method === 'custom') {
            $ssl = app(IssueDomainCertificate::class)->nonWildcard($this->domain, $component->server);

            /** @var Webserver $webserver */
            $webserver = $component->server->webserver()?->handler();
            $webserver->setupSSL($ssl);
        }

        // A non-wildcard letsencrypt domain needs no Ssl row: Caddy issues from the bare address.
        app(SyncComponentVhost::class)->sync($component->fresh() ?? $component);
        $this->markStatus(EnvironmentDomainStatus::ACTIVE);
    }

    private function markStatus(EnvironmentDomainStatus $status): void
    {
        $this->domain->status = $status;
        $this->domain->save();
    }

    private function prepareWildcardChain(Component $component): void
    {
        $server = $component->server;
        $domain = app(IssueDomainCertificate::class)->wildcardDomain($this->domain, $server);

        $ssl = new Ssl([
            'server_id' => $server->id,
            'domain_id' => $domain->id,
            'type' => 'letsencrypt',
            'status' => SslStatus::CREATING,
            'is_wildcard' => true,
            'domains' => [$domain->domain, '*.'.$domain->domain],
            'email' => app(IssueDomainCertificate::class)->resolveEmail($server),
        ]);
        $ssl->log_id = ServerLog::log($server, 'create-wildcard-ssl', '')->id;
        $ssl->save();

        $this->domain->ssl_id = $ssl->id;
        $this->domain->status = EnvironmentDomainStatus::CREATING;
        $this->domain->save();

        $this->chainAfterRun = ['server' => $server, 'ssl' => $ssl];
    }
}
