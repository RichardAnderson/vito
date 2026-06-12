<?php

namespace Tests\Feature\Pages;

use App\Http\Controllers\PageController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_poc_page_renders_for_authorized_user(): void
    {
        $this->actingAs($this->user);

        $this->get(route('pages.server.poc', ['server' => $this->server]))
            ->assertSuccessful()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dynamic/page')
                ->where('area', 'server')
                ->where('page.id', 'server.poc')
                ->has('schema.0.children.0.children', 3)
            );
    }

    public function test_poc_page_forbidden_for_non_member(): void
    {
        $other = User::factory()->create();
        $other->ensureHasDefaultProject();

        $this->actingAs($other);

        $this->get(route('pages.server.poc', ['server' => $this->server]))
            ->assertForbidden();
    }

    public function test_poc_page_not_found_for_unknown_server(): void
    {
        $this->actingAs($this->user);

        $this->get(route('pages.server.poc', ['server' => 99999999]))
            ->assertNotFound();
    }

    public function test_route_without_a_registered_page_404s(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('pages-test/missing', [PageController::class, 'show'])
            ->defaults('_page', 'does-not-exist')
            ->name('pages-test.missing');

        $this->actingAs($this->user);

        $this->get('pages-test/missing')->assertNotFound();
    }
}
