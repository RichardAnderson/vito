<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Jobs\Network\ConfigureWireguardMemberJob;
use App\Jobs\Network\SyncWireguardPeersJob;
use App\Jobs\Network\TeardownWireguardMemberJob;
use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkMember;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrivateNetworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_cards_with_no_selection(): void
    {
        $this->actingAs($this->user);

        PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.10.0.0/24']);
        PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.11.0.0/24']);

        $this->get(route('networks'))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('networks/index')
                ->where('selectedId', null)
                ->where('members', null)
                ->has('networks', 2)
                ->has('suggestedSubnet'));
    }

    public function test_show_renders_index_with_selected_network(): void
    {
        $this->actingAs($this->user);

        PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.10.0.0/24']);
        $b = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.11.0.0/24']);

        $this->get(route('networks.show', $b))
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('networks/index')
                ->where('selectedId', $b->id)
                ->has('networks', 2)
                ->has('members'));
    }

    public function test_create_private_network(): void
    {
        $this->actingAs($this->user);

        $this->post(route('networks.store'), [
            'name' => 'office',
            'subnet' => '10.88.0.0/24',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_networks', [
            'project_id' => $this->user->current_project_id,
            'name' => 'office',
            'subnet' => '10.88.0.0/24',
            'status' => PrivateNetworkStatus::READY->value,
        ]);
    }

    public function test_create_rejects_public_subnet(): void
    {
        $this->actingAs($this->user);

        $this->post(route('networks.store'), [
            'name' => 'bad',
            'subnet' => '8.8.8.0/24',
        ])->assertSessionHasErrors('subnet');
    }

    public function test_create_rejects_overlapping_subnet(): void
    {
        $this->actingAs($this->user);

        PrivateNetwork::factory()->create([
            'project_id' => $this->user->current_project_id,
            'subnet' => '10.88.0.0/24',
        ]);

        $this->post(route('networks.store'), [
            'name' => 'overlap',
            'subnet' => '10.88.0.128/25',
        ])->assertSessionHasErrors('subnet');
    }

    public function test_create_allows_subnet_used_in_another_project(): void
    {
        $this->actingAs($this->user);

        $otherProject = Project::factory()->create();
        PrivateNetwork::factory()->create([
            'project_id' => $otherProject->id,
            'subnet' => '10.88.0.0/24',
        ]);

        $this->post(route('networks.store'), [
            'name' => 'office',
            'subnet' => '10.88.0.0/24',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_networks', [
            'project_id' => $this->user->current_project_id,
            'subnet' => '10.88.0.0/24',
        ]);
    }

    public function test_attach_server_allocates_and_dispatches(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create([
            'project_id' => $this->user->current_project_id,
            'subnet' => '10.88.0.0/24',
        ]);

        $this->post(route('networks.servers.attach', $network), [
            'server_id' => $this->server->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_network_members', [
            'private_network_id' => $network->id,
            'server_id' => $this->server->id,
            'overlay_ip' => '10.88.0.1',
            'interface' => 'wg0',
            'listen_port' => 51820,
            'status' => MemberStatus::JOINING->value,
        ]);

        $this->assertDatabaseHas('private_networks', [
            'id' => $network->id,
            'status' => PrivateNetworkStatus::UPDATING->value,
        ]);

        Queue::assertPushed(ConfigureWireguardMemberJob::class);
    }

    public function test_server_can_join_multiple_networks_with_distinct_iface_and_port(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $a = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        $b = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.99.0.0/24']);

        $this->post(route('networks.servers.attach', $a), ['server_id' => $this->server->id])->assertSessionDoesntHaveErrors();
        $this->post(route('networks.servers.attach', $b), ['server_id' => $this->server->id])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_network_members', [
            'private_network_id' => $b->id, 'server_id' => $this->server->id, 'interface' => 'wg1', 'listen_port' => 51821,
        ]);
    }

    public function test_attach_rejects_cross_project_server(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);

        $otherProject = Project::factory()->create();
        $otherServer = Server::factory()->create(['project_id' => $otherProject->id]);

        $this->post(route('networks.servers.attach', $network), [
            'server_id' => $otherServer->id,
        ])->assertSessionHasErrors('server_id');

        $this->assertDatabaseMissing('private_network_members', ['server_id' => $otherServer->id]);
    }

    public function test_attach_rejects_duplicate_membership(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $this->server->id,
        ]);

        $this->post(route('networks.servers.attach', $network), [
            'server_id' => $this->server->id,
        ])->assertSessionHasErrors('server_id');
    }

    public function test_detach_marks_leaving_and_dispatches_teardown(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        $member = PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $this->server->id, 'status' => MemberStatus::ACTIVE,
        ]);

        $this->delete(route('networks.servers.detach', ['network' => $network->id, 'server' => $this->server->id]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_network_members', [
            'id' => $member->id, 'status' => MemberStatus::LEAVING->value,
        ]);

        Queue::assertPushed(TeardownWireguardMemberJob::class);
    }

    public function test_leaving_overlay_ip_is_not_reallocated(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);

        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id,
            'server_id' => $this->server->id,
            'overlay_ip' => '10.88.0.1',
            'status' => MemberStatus::LEAVING,
        ]);

        $newServer = Server::factory()->create(['project_id' => $this->user->current_project_id]);

        $this->post(route('networks.servers.attach', $network), ['server_id' => $newServer->id])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('private_network_members', [
            'server_id' => $newServer->id, 'overlay_ip' => '10.88.0.2',
        ]);
        $this->assertDatabaseMissing('private_network_members', [
            'server_id' => $newServer->id, 'overlay_ip' => '10.88.0.1',
        ]);
    }

    public function test_delete_network_dispatches_teardown_and_removes_row(): void
    {
        Queue::fake();
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $this->server->id, 'status' => MemberStatus::ACTIVE,
        ]);

        $this->delete(route('networks.destroy', $network))->assertRedirect(route('networks'));

        $this->assertDatabaseMissing('private_networks', ['id' => $network->id]);
        Queue::assertPushed(TeardownWireguardMemberJob::class);
    }

    public function test_cannot_view_network_in_another_project(): void
    {
        $this->actingAs($this->user);

        $otherProject = Project::factory()->create();
        $network = PrivateNetwork::factory()->create(['project_id' => $otherProject->id]);

        $this->get(route('networks.show', $network))->assertNotFound();
    }

    public function test_read_only_user_cannot_create(): void
    {
        $reader = User::factory()->create();
        $project = $this->user->currentProject;
        $project->users()->create([
            'project_id' => $project->id,
            'user_id' => $reader->id,
            'role' => UserRole::USER,
        ]);
        $reader->update(['current_project_id' => $project->id]);

        $this->actingAs($reader)
            ->post(route('networks.store'), ['name' => 'x', 'subnet' => '10.88.0.0/24'])
            ->assertForbidden();
    }

    public function test_configure_job_installs_service_and_creates_firewall_rules(): void
    {
        \App\Facades\SSH::fake('wg-public-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxx=');
        $this->actingAs($this->user);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        $member = PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id,
            'server_id' => $this->server->id,
            'overlay_ip' => '10.88.0.1',
            'interface' => 'wg0',
            'listen_port' => 51820,
            'public_key' => null,
            'status' => MemberStatus::JOINING,
        ]);

        (new ConfigureWireguardMemberJob($member))->handle();

        $member->refresh();
        $this->assertEquals(MemberStatus::ACTIVE, $member->status);
        $this->assertNotNull($member->public_key);
        $this->assertEquals($this->server->ip.':51820', $member->endpoint);

        $this->assertDatabaseHas('services', ['server_id' => $this->server->id, 'name' => 'wireguard', 'type' => 'vpn']);
        $this->assertDatabaseHas('firewall_rules', [
            'server_id' => $this->server->id, 'private_network_member_id' => $member->id, 'name' => 'WG(wg0) HS', 'protocol' => 'udp', 'port' => '51820',
        ]);
        $this->assertDatabaseHas('firewall_rules', [
            'server_id' => $this->server->id, 'private_network_member_id' => $member->id, 'name' => 'WG(wg0) NET', 'protocol' => 'any', 'source' => '10.88.0.0', 'mask' => '24',
        ]);
    }

    public function test_create_many_firewall_rules_triggers_a_single_apply(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        $member = PrivateNetworkMember::factory()->create(['private_network_id' => $network->id, 'server_id' => $this->server->id]);

        app(\App\Actions\FirewallRule\ManageRule::class)->createMany($this->server, [
            ['name' => 'WG(wg0) HS', 'type' => 'allow', 'protocol' => 'udp', 'port' => '51820', 'source_any' => true],
            ['name' => 'WG(wg0) NET', 'type' => 'allow', 'protocol' => 'any', 'source' => '10.88.0.0', 'mask' => 24],
        ], $member->id);

        $this->assertDatabaseHas('firewall_rules', ['name' => 'WG(wg0) HS', 'private_network_member_id' => $member->id]);
        $this->assertDatabaseHas('firewall_rules', ['name' => 'WG(wg0) NET', 'private_network_member_id' => $member->id]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\FirewallRule\ApplyRulesJob::class, 1);
    }

    public function test_create_rejects_subnet_containing_a_server_private_ip(): void
    {
        $this->actingAs($this->user);

        $this->server->update(['local_ip' => '10.4.0.2']);

        $this->post(route('networks.store'), [
            'name' => 'clash',
            'subnet' => '10.4.0.0/24',
        ])->assertSessionHasErrors('subnet');

        $this->assertDatabaseMissing('private_networks', ['subnet' => '10.4.0.0/24']);
    }

    public function test_cannot_transfer_server_attached_to_network(): void
    {
        $this->actingAs($this->user);

        $target = Project::factory()->create();
        $target->users()->create([
            'project_id' => $target->id,
            'user_id' => $this->user->id,
            'role' => UserRole::OWNER,
        ]);

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $this->server->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Actions\Server\TransferServer::class)->transfer($this->user, $this->server, ['project_id' => $target->id]);
    }

    public function test_server_deletion_resyncs_surviving_members(): void
    {
        Queue::fake();

        $network = PrivateNetwork::factory()->create(['project_id' => $this->user->current_project_id, 'subnet' => '10.88.0.0/24']);
        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $this->server->id, 'overlay_ip' => '10.88.0.1', 'status' => MemberStatus::ACTIVE,
        ]);
        $survivor = Server::factory()->create(['project_id' => $this->user->current_project_id]);
        PrivateNetworkMember::factory()->create([
            'private_network_id' => $network->id, 'server_id' => $survivor->id, 'overlay_ip' => '10.88.0.2', 'interface' => 'wg0', 'listen_port' => 51821, 'status' => MemberStatus::ACTIVE,
        ]);

        $this->server->deleteFromProvider = false;
        $this->server->delete();

        Queue::assertPushed(SyncWireguardPeersJob::class);
    }
}
