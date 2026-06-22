<?php

namespace Vito\Plugin\Contracts;

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

interface Registrar
{
    public function command(string $class): CommandRegistrar;

    public function views(string $name): ViewsRegistrar;

    public function siteType(string $id): SiteTypeRegistrar;

    public function serverProvider(string $id): ServerProviderRegistrar;

    public function storageProvider(string $id): StorageProviderRegistrar;

    public function sourceControl(string $id): SourceControlRegistrar;

    public function dnsProvider(string $id): DnsProviderRegistrar;

    public function notificationChannel(string $id): NotificationChannelRegistrar;

    public function serviceType(string $id): ServiceTypeRegistrar;

    public function serverFeature(string $name): ServerFeatureRegistrar;

    public function serverFeatureAction(string $feature, string $name): ServerFeatureActionRegistrar;

    public function siteFeature(string $type, string $name): SiteFeatureRegistrar;

    public function siteFeatureAction(string $type, string $feature, string $name): SiteFeatureActionRegistrar;

    public function workflowAction(string $name): WorkflowActionRegistrar;
}
