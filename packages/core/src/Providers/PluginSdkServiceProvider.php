<?php

namespace App\Providers;

use App\Plugins\Registrar;
use App\Plugins\Sdk\BroadcastAdapter;
use Illuminate\Support\ServiceProvider;
use Vito\Plugin\Contracts\Broadcast;
use Vito\Plugin\Contracts\Http;
use Vito\Plugin\Contracts\Notifications;
use Vito\Plugin\Contracts\Registrar as RegistrarContract;
use Vito\Plugin\Contracts\Ssh;
use Vito\Plugin\Contracts\Storage;
use Vito\Plugin\Exceptions\CapabilityBindingUnavailable;

/**
 * Binds the Plugin SDK Host API capability facades to their concrete host implementations.
 *
 * Three facades have real, safe Vito backing and are bound now. Http and Storage are
 * reserved: their contracts/facades are published (so the type surface is stable) but they
 * resolve to a throwing closure until the capability chokepoint — manifest assertCapability()
 * plus, for Storage, server-scoped path policy — lands. Binding them straight to the framework
 * HTTP/filesystem stack now would expose an ungated privileged surface (host local disk),
 * which the security rules forbid.
 */
final class PluginSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Ssh::class, fn () => $this->app->make('ssh'));
        $this->app->bind(Notifications::class, fn () => $this->app->make('notifier'));
        $this->app->bind(Broadcast::class, fn () => new BroadcastAdapter);
        $this->app->bind(RegistrarContract::class, fn () => new Registrar);

        $this->app->bind(Http::class, fn () => throw CapabilityBindingUnavailable::for('outbound-http'));
        $this->app->bind(Storage::class, fn () => throw CapabilityBindingUnavailable::for('storage'));
    }
}
