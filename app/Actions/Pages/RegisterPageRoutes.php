<?php

namespace App\Actions\Pages;

use App\Http\Controllers\PageController;
use App\Pages\AbstractPage;
use App\Pages\ExtensionActionRegistry;
use App\Pages\PageComponentException;
use App\Pages\PageRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Routing strategy (a): register a real, named route per framework page at boot.
 *
 * Runs from a booted() callback after plugin pages are registered and after the
 * Spatie route-attribute routes exist, so the collision check sees the full router.
 * Routes point at the static PageController (cacheable); the page/handler is resolved
 * at request time from the registry via the `_page` route default. Skipped when routes
 * are compiled (the cache already contains them); the lifecycle FlushRouteCaches drops
 * that cache so the next request re-registers from the live registry.
 */
final readonly class RegisterPageRoutes
{
    public function __construct(
        private PageRegistry $registry,
        private ExtensionActionRegistry $extensionActions,
    ) {}

    public function handle(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        $this->registerExtensionActionRoutes();

        foreach ($this->registry->all() as $page) {
            $area = $page->area();
            $middleware = array_merge(['web'], $area->middleware());
            $uri = trim($area->routePrefix(), '/').'/'.ltrim($page->slug(), '/');

            if ($this->collides($uri, 'GET')) {
                Log::warning("Skipping framework page route '{$page::id()}': URI '{$uri}' collides with an existing route.");

                continue;
            }

            Route::middleware($middleware)
                ->get($uri, [PageController::class, 'show'])
                ->defaults('_page', $page::id())
                ->name($page->routeName());

            $this->assertNoAddressCollision($page);

            foreach ($page->allActions() as $action) {
                if (! $action->hasAuthorization()) {
                    Log::error("Skipping page action '{$page::id()}.{$action->id()}': a mutation must declare an authorize callback.");

                    continue;
                }

                $actionUri = "{$uri}/actions/{$action->id()}";
                if ($this->collides($actionUri, strtoupper($action->getMethod()))) {
                    Log::warning("Skipping page action route '{$page::id()}.{$action->id()}': URI collides.");

                    continue;
                }

                Route::middleware($middleware)
                    ->{$action->getMethod()}($actionUri, [PageController::class, 'action'])
                    ->defaults('_page', $page::id())
                    ->defaults('_action', $action->id())
                    ->name($action->getRouteName("{$page->routeName()}.{$action->id()}"));
            }

            foreach ($page->allData() as $endpoint) {
                if (! $endpoint->hasAuthorization()) {
                    Log::error("Skipping data endpoint '{$page::id()}.{$endpoint->id()}': declare authorize() or inheritCanView().");

                    continue;
                }

                $dataUri = "{$uri}/data/{$endpoint->id()}";
                if ($this->collides($dataUri, strtoupper($endpoint->getMethod()))) {
                    Log::warning("Skipping data endpoint route '{$page::id()}.{$endpoint->id()}': URI collides.");

                    continue;
                }

                Route::middleware($middleware)
                    ->{$endpoint->getMethod()}($dataUri, [PageController::class, 'data'])
                    ->defaults('_page', $page::id())
                    ->defaults('_endpoint', $endpoint->id())
                    ->name($endpoint->getRouteName("{$page->routeName()}.{$endpoint->id()}"));
            }
        }

        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * Action and data route names share the flat `{page-route}.{id}` namespace
     * (no `.actions.`/`.data.` infix), so an action id colliding with a data id
     * would silently overwrite a route name. Fail loudly at boot instead.
     */
    private function assertNoAddressCollision(AbstractPage $page): void
    {
        $actionNames = array_map(
            fn ($action): ?string => $action->getRouteName("{$page->routeName()}.{$action->id()}"),
            $page->allActions(),
        );
        $dataNames = array_map(
            fn ($endpoint): ?string => $endpoint->getRouteName("{$page->routeName()}.{$endpoint->id()}"),
            $page->allData(),
        );

        $collisions = array_intersect($actionNames, $dataNames);
        if ($collisions !== []) {
            throw new PageComponentException(
                "Page '{$page::id()}' has colliding action/data route name(s): ".implode(', ', $collisions).'.',
            );
        }
    }

    private function registerExtensionActionRoutes(): void
    {
        foreach ($this->extensionActions->all() as $action) {
            if (! $action->hasArea()) {
                Log::error("Skipping extension action '{$action->plugin()}.{$action->name()}': no area() declared.");

                continue;
            }

            if (! $action->hasAuthorization()) {
                Log::error("Skipping extension action '{$action->plugin()}.{$action->name()}': a mutation must declare an authorize callback.");

                continue;
            }

            $area = app($action->getAreaClass());
            $uri = trim($area->routePrefix(), '/')."/ext/{$action->plugin()}/{$action->name()}";

            if ($this->collides($uri, strtoupper($action->getMethod()))) {
                Log::warning("Skipping extension action route '{$action->plugin()}.{$action->name()}': URI collides.");

                continue;
            }

            Route::middleware(array_merge(['web'], $area->middleware()))
                ->{$action->getMethod()}($uri, [PageController::class, 'extensionAction'])
                ->defaults('_ext_plugin', $action->plugin())
                ->defaults('_ext_action', $action->name())
                ->name("ext.{$action->plugin()}.{$action->name()}");
        }
    }

    private function collides(string $uri, string $method): bool
    {
        $target = $this->normalize($uri);

        foreach (Route::getRoutes() as $route) {
            if (! in_array($method, $route->methods(), true)) {
                continue;
            }

            if ($this->normalize($route->uri()) === $target) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $uri): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/')) ?? $uri;
    }
}
