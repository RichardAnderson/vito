<?php

namespace App\Plugins;

use Vito\Plugin\Contracts\Registrar as RegistrarContract;
use Vito\Plugin\Contracts\Registrars\CommandRegistrar;
use Vito\Plugin\Contracts\Registrars\DnsProviderRegistrar;
use Vito\Plugin\Contracts\Registrars\NotificationChannelRegistrar;
use Vito\Plugin\Contracts\Registrars\ServerFeatureActionRegistrar;
use Vito\Plugin\Contracts\Registrars\ServerFeatureRegistrar;
use Vito\Plugin\Contracts\Registrars\ServerProviderRegistrar;
use Vito\Plugin\Contracts\Registrars\ServiceTypeRegistrar;
use Vito\Plugin\Contracts\Registrars\SiteFeatureActionRegistrar;
use Vito\Plugin\Contracts\Registrars\SiteFeatureRegistrar;
use Vito\Plugin\Contracts\Registrars\SiteTypeRegistrar;
use Vito\Plugin\Contracts\Registrars\SourceControlRegistrar;
use Vito\Plugin\Contracts\Registrars\StorageProviderRegistrar;
use Vito\Plugin\Contracts\Registrars\ViewsRegistrar;
use Vito\Plugin\Contracts\Registrars\WorkflowActionRegistrar;

final class Registrar implements RegistrarContract
{
    public function command(string $class): CommandRegistrar
    {
        return new RegisterCommand($class);
    }

    public function views(string $name): ViewsRegistrar
    {
        return new RegisterViews($name);
    }

    public function siteType(string $id): SiteTypeRegistrar
    {
        return new RegisterSiteType($id);
    }

    public function serverProvider(string $id): ServerProviderRegistrar
    {
        return new RegisterServerProvider($id);
    }

    public function storageProvider(string $id): StorageProviderRegistrar
    {
        return new RegisterStorageProvider($id);
    }

    public function sourceControl(string $id): SourceControlRegistrar
    {
        return new RegisterSourceControl($id);
    }

    public function dnsProvider(string $id): DnsProviderRegistrar
    {
        return new RegisterDNSProvider($id);
    }

    public function notificationChannel(string $id): NotificationChannelRegistrar
    {
        return new RegisterNotificationChannel($id);
    }

    public function serviceType(string $id): ServiceTypeRegistrar
    {
        return new RegisterServiceType($id);
    }

    public function serverFeature(string $name): ServerFeatureRegistrar
    {
        return new RegisterServerFeature($name);
    }

    public function serverFeatureAction(string $feature, string $name): ServerFeatureActionRegistrar
    {
        return new RegisterServerFeatureAction($feature, $name);
    }

    public function siteFeature(string $type, string $name): SiteFeatureRegistrar
    {
        return new RegisterSiteFeature($type, $name);
    }

    public function siteFeatureAction(string $type, string $feature, string $name): SiteFeatureActionRegistrar
    {
        return new RegisterSiteFeatureAction($type, $feature, $name);
    }

    public function workflowAction(string $name): WorkflowActionRegistrar
    {
        return new RegisterWorkflowAction($name);
    }
}
