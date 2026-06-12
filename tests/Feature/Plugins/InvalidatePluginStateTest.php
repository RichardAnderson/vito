<?php

namespace Tests\Feature\Plugins;

use App\Actions\Plugins\FlushRouteCaches;
use App\Actions\Plugins\InvalidatePluginState;
use App\Actions\Ziggy\GetZiggyRoutes;
use App\Events\SocketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InvalidatePluginStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgets_ziggy_caches_and_broadcasts_invalidation(): void
    {
        Cache::forever(GetZiggyRoutes::SCRIPT_CACHE_KEY, 'stale-script');
        Cache::forever(GetZiggyRoutes::VERSION_CACHE_KEY, 'stale-version');
        Event::fake([SocketEvent::class]);

        app(InvalidatePluginState::class)->handle();

        $this->assertNull(Cache::get(GetZiggyRoutes::SCRIPT_CACHE_KEY));
        $this->assertNull(Cache::get(GetZiggyRoutes::VERSION_CACHE_KEY));
        Event::assertDispatched(SocketEvent::class);
    }

    public function test_flush_route_caches_removes_compiled_routes_file(): void
    {
        $path = app()->getCachedRoutesPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php return [];');

        app(FlushRouteCaches::class)->handle();

        $this->assertFileDoesNotExist($path);
    }
}
