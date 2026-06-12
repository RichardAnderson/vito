<?php

namespace App\Actions\Plugins;

use App\Actions\Bootstrap\GetBootstrap;
use App\Actions\Ziggy\GetZiggyRoutes;
use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;

final readonly class InvalidatePluginState
{
    public function __construct(
        private PluginCache $cache,
        private FlushRouteCaches $flushRouteCaches,
    ) {}

    /**
     * Shared tail for every plugin lifecycle change (install/enable/disable/uninstall).
     * Clears the active-plugin cache, flushes the route cache AND the Ziggy client
     * route-script cache (otherwise client `route()` lookups for page routes go stale
     * forever), recomputes the bootstrap version and broadcasts the invalidation.
     */
    public function handle(): void
    {
        $this->cache->clear();
        $this->flushRouteCaches->handle();
        GetZiggyRoutes::forgetCache();

        GetBootstrap::forgetVersion();
        $newVersion = app(GetBootstrap::class)->computeVersion();
        SocketEvent::dispatch(new SocketEventDTO(0, 'bootstrap.invalidated', ['version' => $newVersion]));
    }
}
