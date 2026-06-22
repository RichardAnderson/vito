<?php

namespace App\Observers;

use App\Jobs\SSL\DeleteSiteSslJob;
use App\Models\Site;
use App\Models\Ssl;

/**
 * App-side cleanup that core's Site model must not own (dispatches an app Job). Core's own deleting
 * hook keeps the in-process cleanup (workers/deployments/git hook); this dispatches the SSL teardown.
 */
final class SiteSslObserver
{
    public function deleting(Site $site): void
    {
        $site->ssls()->each(function (Ssl $ssl) use ($site): void {
            dispatch(new DeleteSiteSslJob($site->server, $ssl))->onQueue('ssh');
        });
    }
}
