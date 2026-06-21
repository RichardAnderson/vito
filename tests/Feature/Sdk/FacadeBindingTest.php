<?php

namespace Tests\Feature\Sdk;

use App\Facades\SSH;
use App\Helpers\Notifier;
use App\Plugins\Sdk\BroadcastAdapter;
use App\Support\Testing\SSHFake;
use Tests\TestCase;
use Vito\Plugin\Contracts\Broadcast as BroadcastContract;
use Vito\Plugin\Contracts\Http as HttpContract;
use Vito\Plugin\Contracts\Notifications as NotificationsContract;
use Vito\Plugin\Contracts\Ssh as SshContract;
use Vito\Plugin\Contracts\Storage as StorageContract;
use Vito\Plugin\Exceptions\CapabilityBindingUnavailable;

class FacadeBindingTest extends TestCase
{
    public function test_ssh_contract_resolves_to_the_host_ssh_helper(): void
    {
        SSH::fake();

        $this->assertInstanceOf(SSHFake::class, app(SshContract::class));
    }

    public function test_notifications_contract_resolves_to_the_host_notifier(): void
    {
        $this->assertInstanceOf(Notifier::class, app(NotificationsContract::class));
    }

    public function test_broadcast_contract_resolves_to_the_socket_event_adapter(): void
    {
        $this->assertInstanceOf(BroadcastAdapter::class, app(BroadcastContract::class));
    }

    public function test_http_capability_is_reserved_and_throws(): void
    {
        $this->expectException(CapabilityBindingUnavailable::class);

        app(HttpContract::class);
    }

    public function test_storage_capability_is_reserved_and_throws(): void
    {
        $this->expectException(CapabilityBindingUnavailable::class);

        app(StorageContract::class);
    }
}
