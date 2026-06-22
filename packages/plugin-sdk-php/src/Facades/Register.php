<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Registrar as RegistrarContract;

/**
 * @method static \Vito\Plugin\Contracts\Registrars\CommandRegistrar command(string $class)
 * @method static \Vito\Plugin\Contracts\Registrars\ViewsRegistrar views(string $name)
 * @method static \Vito\Plugin\Contracts\Registrars\SiteTypeRegistrar siteType(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\ServerProviderRegistrar serverProvider(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\StorageProviderRegistrar storageProvider(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\SourceControlRegistrar sourceControl(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\DnsProviderRegistrar dnsProvider(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\NotificationChannelRegistrar notificationChannel(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\ServiceTypeRegistrar serviceType(string $id)
 * @method static \Vito\Plugin\Contracts\Registrars\ServerFeatureRegistrar serverFeature(string $name)
 * @method static \Vito\Plugin\Contracts\Registrars\ServerFeatureActionRegistrar serverFeatureAction(string $feature, string $name)
 * @method static \Vito\Plugin\Contracts\Registrars\SiteFeatureRegistrar siteFeature(string $type, string $name)
 * @method static \Vito\Plugin\Contracts\Registrars\SiteFeatureActionRegistrar siteFeatureAction(string $type, string $feature, string $name)
 * @method static \Vito\Plugin\Contracts\Registrars\WorkflowActionRegistrar workflowAction(string $name)
 *
 * @see RegistrarContract
 */
final class Register extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RegistrarContract::class;
    }
}
