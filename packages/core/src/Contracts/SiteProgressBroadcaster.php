<?php

namespace App\Contracts;

use App\Models\Site;

/**
 * Core binding-seam for broadcasting site progress (the SiteResource wire shape lives in the app).
 * Concrete: App\Broadcasting\SiteProgressBroadcaster (app). Core calls it only when bound, so a
 * core-only consumer (plugin testbench) degrades to no broadcast rather than coupling to App\Http.
 */
interface SiteProgressBroadcaster
{
    public function broadcast(Site $site): void;
}
