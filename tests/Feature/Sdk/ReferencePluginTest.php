<?php

namespace Tests\Feature\Sdk;

use App\Plugins\Runtime\GetPluginInstance;
use App\Events\SocketEvent;
use App\Facades\SSH;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Plugins\Example\Actions\Ping;
use Tests\Fixtures\Plugins\Example\Plugin as ExamplePlugin;
use Tests\TestCase;
use Vito\Plugin\PluginInterface;

class ReferencePluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_plugin_implements_the_sdk_interface(): void
    {
        $this->assertInstanceOf(PluginInterface::class, new ExamplePlugin);
    }

    public function test_get_plugin_instance_accepts_an_sdk_interface_plugin(): void
    {
        $plugin = Plugin::create([
            'name' => 'Example',
            'version' => '1.0.0',
            'description' => 'Reference plugin',
            'repo' => 'vito/example',
            'namespace' => ExamplePlugin::class,
            'folder' => 'Example',
            'username' => 'vito',
            'is_installed' => true,
            'is_enabled' => true,
        ]);

        $instance = app(GetPluginInstance::class)->handle($plugin);

        $this->assertInstanceOf(ExamplePlugin::class, $instance);
        $this->assertSame('Example', $instance->getName());
    }

    public function test_plugin_action_consumes_contracts_and_capability_facades(): void
    {
        Event::fake([SocketEvent::class]);
        SSH::fake('pong');

        $output = app(Ping::class)->handle($this->server, $this->site);

        $this->assertSame('pong', $output);
        Event::assertDispatched(
            SocketEvent::class,
            fn (SocketEvent $event): bool => $event->data->type === 'example.pinged'
                && $event->data->projectId === $this->server->project_id,
        );
    }
}
