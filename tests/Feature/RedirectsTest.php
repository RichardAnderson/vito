<?php

namespace Tests\Feature;

use App\Enums\RedirectStatus;
use App\Enums\UserRole;
use App\Facades\SSH;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_see_redirects(): void
    {
        $this->actingAs($this->user);

        Redirect::factory()->create(['site_id' => $this->site->id]);

        $this->get(route('redirects', ['server' => $this->server, 'site' => $this->site]))
            ->assertSuccessful()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dynamic/page')
                ->where('area', 'site')
                ->has('tables:redirects'));
    }

    public function test_create_redirect(): void
    {
        SSH::fake();

        $this->actingAs($this->user);

        $this->post(route('redirects.store', ['server' => $this->server, 'site' => $this->site]), [
            'from' => 'some-path',
            'to' => 'https://example.com/redirect',
            'mode' => 301,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('redirects', [
            'from' => 'some-path',
            'to' => 'https://example.com/redirect',
            'mode' => 301,
            'status' => RedirectStatus::READY,
        ]);
    }

    public function test_create_redirect_validates(): void
    {
        $this->actingAs($this->user);

        $this->post(route('redirects.store', ['server' => $this->server, 'site' => $this->site]), [
            'from' => '',
            'to' => 'not-a-url',
            'mode' => 999,
        ])->assertSessionHasErrors(['from', 'to', 'mode']);
    }

    public function test_delete_redirect(): void
    {
        SSH::fake();

        $this->actingAs($this->user);

        $redirect = Redirect::factory()->create(['site_id' => $this->site->id]);

        $this->delete(route('redirects.destroy', ['server' => $this->server, 'site' => $this->site]), [
            'redirect' => $redirect->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }

    public function test_create_requires_write_access(): void
    {
        $this->server->project->users()->where('user_id', $this->user->id)->update(['role' => UserRole::USER]);
        $this->user->refresh();

        $this->actingAs($this->user);

        $this->post(route('redirects.store', ['server' => $this->server, 'site' => $this->site]), [
            'from' => 'some-path',
            'to' => 'https://example.com/redirect',
            'mode' => 301,
        ])->assertForbidden();
    }
}
