<?php

namespace App\Actions\Plugins;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

final readonly class FlushRouteCaches
{
    /**
     * Drop the compiled route cache so the next request re-registers page routes
     * from the live registry (routing strategy (a)). Without this, a cached route
     * for a now-disabled plugin would keep resolving until a manual cache clear.
     */
    public function handle(): void
    {
        $cachedRoutes = app()->getCachedRoutesPath();

        if (File::exists($cachedRoutes)) {
            Artisan::call('route:clear');
        }
    }
}
