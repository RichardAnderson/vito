<?php

namespace App\Providers;

use App\Actions\Pages\RegisterPageRoutes;
use App\Pages\Areas\ServerArea;
use App\Pages\Areas\SiteArea;
use App\Pages\ExtensionActionRegistry;
use App\Pages\ExtensionRegistry;
use App\Pages\HostedDomains\HostedDomainsPage;
use App\Pages\PageRegistry;
use App\Pages\Redirects\RedirectsPage;
use App\Pages\Server\ServerPocPage;
use App\Pages\SiteCronJobs\SiteCronJobsPage;
use App\Pages\SiteSettings\SiteSettingsPage;
use App\Pages\SiteStats\SiteStatsPage;
use App\Pages\SiteTooling\SiteToolingPage;
use App\Pages\SiteWorkers\SiteWorkersPage;
use Illuminate\Support\ServiceProvider;

class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageRegistry::class);

        // Scoped (like the inertia-table HookRegistry): extenders are re-registered
        // from plugin boot() every request, so the registry must reset per request
        // to avoid accumulation under a persistent worker (Octane).
        $this->app->scoped(ExtensionRegistry::class);
        $this->app->scoped(ExtensionActionRegistry::class);
    }

    public function boot(): void
    {
        $this->registerCore();

        // Runs after PluginsServiceProvider's booted callback (BootPlugins → plugin
        // RegisterPage calls) and after the Spatie attribute routes exist, so the
        // collision check sees the full router and plugin pages are included.
        $this->app->booted(function (): void {
            app(RegisterPageRoutes::class)->handle();
        });
    }

    private function registerCore(): void
    {
        $registry = app(PageRegistry::class);

        $registry->registerArea(app(ServerArea::class));
        $registry->registerArea(app(SiteArea::class));

        $registry->register(app(ServerPocPage::class));
        $registry->register(app(SiteSettingsPage::class));
        $registry->register(app(RedirectsPage::class));
        $registry->register(app(SiteCronJobsPage::class));
        $registry->register(app(HostedDomainsPage::class));
        $registry->register(app(SiteStatsPage::class));
        $registry->register(app(SiteToolingPage::class));
        $registry->register(app(SiteWorkersPage::class));
    }
}
