<?php

namespace App\Providers;

use App\Helpers\FTP;
use App\Helpers\Notifier;
use App\Helpers\SFTP;
use App\Helpers\SSH;
use Illuminate\Support\ServiceProvider;

/**
 * The auto-discovered entry point for the vito/core package. Contributes core's migrations, views,
 * facade bindings, and (later) config + framework sub-providers to any host that installs vito/core —
 * the Vito app in production or a plugin's test harness. The app keeps its own bootstrap/kernels/config.
 */
final class CoreServiceProvider extends ServiceProvider
{
    private const CONFIG_FILES = [
        'app',
        'core',
        'site',
        'service',
        'services',
        'server-provider',
        'serverproviders',
        'storage-provider',
        'source-control',
        'dns-provider',
        'notification-channel',
        'workflow',
    ];

    public function register(): void
    {
        $config = __DIR__.'/../../config';
        foreach (self::CONFIG_FILES as $name) {
            $this->mergeConfigFrom($config.'/'.$name.'.php', $name);
        }

        $this->app->bind('ssh', fn (): SSH => new SSH);
        $this->app->bind('notifier', fn (): Notifier => new Notifier);
        $this->app->bind('ftp', fn (): FTP => new FTP);
        $this->app->bind('sftp', fn (): SFTP => new SFTP);

        $this->app->register(PluginSdkServiceProvider::class);
        $this->app->register(PluginsServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
