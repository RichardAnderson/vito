<?php

namespace App\Actions\EnvironmentDomain;

use App\Actions\SSL\DeleteSsl;
use App\Jobs\Component\SyncComponentVhostJob;
use App\Models\EnvironmentDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteEnvironmentDomain
{
    /**
     * Deleting the row alone would leave the hostname in the component's vhost and the
     * certificate on disk, so the certificate is released first and the vhost regenerated after.
     *
     * @throws ValidationException
     */
    public function delete(EnvironmentDomain $domain, bool $force = false): void
    {
        if (! $force) {
            $this->assertNothingRedirectsHere($domain);
        }

        $component = $domain->terminatingComponent();
        $ssl = $domain->ssl;
        $sslIsShared = $ssl !== null && EnvironmentDomain::query()
            ->where('ssl_id', $ssl->id)
            ->whereKeyNot($domain->id)
            ->exists();

        $domain->delete();

        if ($ssl !== null && ! $sslIsShared) {
            app(DeleteSsl::class)->delete($ssl);
        }

        if ($component !== null) {
            DB::afterCommit(fn () => dispatch(new SyncComponentVhostJob($component))->onQueue('ssh'));
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertNothingRedirectsHere(EnvironmentDomain $domain): void
    {
        $hosts = $domain->redirectedBy()->pluck('host');

        if ($hosts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'host' => 'Remove the redirect(s) pointing here first: '.$hosts->implode(', ').'.',
            ]);
        }
    }
}
