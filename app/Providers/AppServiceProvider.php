<?php

namespace App\Providers;

use App\Contracts\ServerConnectionChecker;
use App\Events\SiteCreatedEvent;
use App\Events\SiteDeletedEvent;
use App\Events\SocketEvent;
use App\Contracts\ServiceManager;
use App\Contracts\SiteProgressBroadcaster as SiteProgressBroadcasterContract;
use App\Contracts\WorkerCreator;
use App\Listeners\HandleSiteCreatedStats;
use App\Listeners\HandleSiteDeletedStats;
use App\Listeners\SocketEventListener;
use App\Models\BackupFile;
use App\Models\Metric;
use App\Models\PersonalAccessToken;
use App\Models\ServerLog;
use App\Models\Site;
use App\Observers\BackupFileObserver;
use App\Observers\MetricObserver;
use App\Observers\ServerLogObserver;
use App\Observers\SiteSslObserver;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Core binding-seam: core models/site-types depend on these contracts; the app binds the Actions.
        $this->app->bind(ServerConnectionChecker::class, \App\Actions\Server\CheckConnection::class);
        $this->app->bind(ServiceManager::class, \App\Actions\Service\Manage::class);
        $this->app->bind(WorkerCreator::class, \App\Actions\Worker\CreateWorker::class);
        $this->app->bind(SiteProgressBroadcasterContract::class, \App\Broadcasting\SiteProgressBroadcaster::class);
    }

    public function boot(): void
    {
        ResourceCollection::withoutWrapping();

        // App-side model side effects that core models must not own (broadcasts / app Jobs / Actions).
        BackupFile::observe(BackupFileObserver::class);
        Metric::observe(MetricObserver::class);
        ServerLog::observe(ServerLogObserver::class);
        Site::observe(SiteSslObserver::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if (config('app.force_https')) {
            URL::forceHttps();
        }

        Event::listen(SocketEvent::class, SocketEventListener::class);
        Event::listen(SiteCreatedEvent::class, HandleSiteCreatedStats::class);
        Event::listen(SiteDeletedEvent::class, HandleSiteDeletedStats::class);
    }
}
