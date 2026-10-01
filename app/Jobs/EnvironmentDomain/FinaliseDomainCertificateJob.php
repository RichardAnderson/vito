<?php

namespace App\Jobs\EnvironmentDomain;

use App\Actions\Component\SyncComponentVhost;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\SslStatus;
use App\Exceptions\SSHError;
use App\Models\EnvironmentDomain;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The tail of the wildcard chain. Issuance is asynchronous, so the SSL-bearing vhost cannot be
 * written in the same pass that requests the certificate — it would point nginx at files that
 * do not exist yet and break every later reload on that server.
 */
class FinaliseDomainCertificateJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected EnvironmentDomain $domain, protected int $serverId) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $this->run("server-{$this->serverId}", function (): void {
            $domain = $this->domain->fresh();

            if ($domain === null) {
                return;
            }

            if ($domain->ssl?->status !== SslStatus::CREATED) {
                $domain->status = EnvironmentDomainStatus::FAILED;
                $domain->save();

                return;
            }

            $component = $domain->terminatingComponent();

            if ($component !== null) {
                app(SyncComponentVhost::class)->sync($component);
            }

            $domain->status = EnvironmentDomainStatus::ACTIVE;
            $domain->save();
        });
    }

    public function failed(Throwable $e): void
    {
        $this->domain->fill(['status' => EnvironmentDomainStatus::FAILED])->save();
    }
}
