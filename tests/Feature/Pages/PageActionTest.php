<?php

namespace Tests\Feature\Pages;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_runs_for_authorized_writer(): void
    {
        $this->actingAs($this->user);

        $this->post(route('pages.server.poc.ping', ['server' => $this->server]))
            ->assertRedirect()
            ->assertSessionHas('success', 'pong');
    }

    public function test_action_forbidden_for_read_only_role(): void
    {
        $this->server->project->users()->where('user_id', $this->user->id)->update([
            'role' => UserRole::USER,
        ]);

        $this->actingAs($this->user);

        $this->post(route('pages.server.poc.ping', ['server' => $this->server]))
            ->assertForbidden();
    }

    public function test_action_not_found_for_foreign_server(): void
    {
        $this->actingAs($this->user);

        $this->post(route('pages.server.poc.ping', ['server' => 99999999]))
            ->assertNotFound();
    }

    public function test_data_endpoint_returns_json_for_viewer(): void
    {
        $this->actingAs($this->user);

        $this->get(route('pages.server.poc.info', ['server' => $this->server]))
            ->assertOk()
            ->assertJson(['name' => $this->server->name]);
    }
}
