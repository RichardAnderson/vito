<?php

namespace Tests\Unit;

use App\Enums\MemberStatus;
use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkMember;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WireguardConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderable_peers_excludes_self_keyless_and_non_active(): void
    {
        $network = PrivateNetwork::factory()->create(['subnet' => '10.88.0.0/24']);

        $self = PrivateNetworkMember::factory()->for($network)->create(['overlay_ip' => '10.88.0.1', 'status' => MemberStatus::ACTIVE]);
        $activePeer = PrivateNetworkMember::factory()->for($network)->create(['overlay_ip' => '10.88.0.2', 'status' => MemberStatus::ACTIVE]);
        PrivateNetworkMember::factory()->for($network)->create(['overlay_ip' => '10.88.0.3', 'status' => MemberStatus::ACTIVE, 'public_key' => null]);
        PrivateNetworkMember::factory()->for($network)->create(['overlay_ip' => '10.88.0.4', 'status' => MemberStatus::JOINING]);

        $peers = $network->renderablePeers($self->server_id);

        $this->assertCount(1, $peers);
        $this->assertEquals($activePeer->id, $peers->first()->id);
    }

    public function test_render_conf_template_produces_full_mesh(): void
    {
        $network = PrivateNetwork::factory()->create(['subnet' => '10.88.0.0/24', 'mtu' => 1420]);
        $peerServer = Server::factory()->create(['ip' => '5.6.7.8']);
        $peer = PrivateNetworkMember::factory()->for($network)->create([
            'server_id' => $peerServer->id,
            'overlay_ip' => '10.88.0.2',
            'public_key' => 'PEERKEY=',
            'endpoint' => '5.6.7.8:51821',
            'status' => MemberStatus::ACTIVE,
        ]);

        $rendered = view('ssh.services.wireguard.render-conf', [
            'iface' => 'wg0',
            'overlayIp' => '10.88.0.1',
            'prefix' => 24,
            'listenPort' => 51820,
            'mtu' => 1420,
            'peers' => collect([$peer]),
        ])->render();

        $this->assertStringContainsString('Address = 10.88.0.1/24', $rendered);
        $this->assertStringContainsString('ListenPort = 51820', $rendered);
        $this->assertStringContainsString('MTU = 1420', $rendered);
        $this->assertStringContainsString('PrivateKey = __VITO_WG_PRIVATE_KEY__', $rendered);
        $this->assertStringContainsString('PublicKey = PEERKEY=', $rendered);
        $this->assertStringContainsString('AllowedIPs = 10.88.0.2/32', $rendered);
        $this->assertStringContainsString('Endpoint = 5.6.7.8:51821', $rendered);
        $this->assertStringContainsString('PersistentKeepalive = 25', $rendered);
    }
}
