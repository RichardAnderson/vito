<?php

use App\Enums\EnvironmentStatus;
use App\Models\Application;
use App\Models\Component;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->application = Application::factory()->create(['project_id' => $this->user->current_project_id]);
});

it('creates an environment with its two deployment scripts', function (): void {
    $this->actingAs($this->user)
        ->post(route('environments.store', $this->application->id), [
            'name' => 'Production',
            'branch' => 'main',
            'color' => 'blue',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $environment = Environment::query()->where('name', 'Production')->firstOrFail();

    expect($environment->slug)->toBe('production')
        ->and($environment->status)->toBe(EnvironmentStatus::READY);

    $this->assertDatabaseHas('deployment_scripts', ['environment_id' => $environment->id, 'name' => 'build']);
    $this->assertDatabaseHas('deployment_scripts', ['environment_id' => $environment->id, 'name' => 'pre-flight']);
});

it('rejects a duplicate environment name in the same application', function (): void {
    Environment::factory()->create(['application_id' => $this->application->id, 'name' => 'Production']);

    $this->actingAs($this->user)
        ->post(route('environments.store', $this->application->id), ['name' => 'Production'])
        ->assertSessionHasErrors('name');
});

it('rejects a name whose slug collides with an existing environment', function (): void {
    Environment::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Production',
        'slug' => 'production',
    ]);

    $this->actingAs($this->user)
        ->post(route('environments.store', $this->application->id), ['name' => 'PRODUCTION!'])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown color', function (): void {
    $this->actingAs($this->user)
        ->post(route('environments.store', $this->application->id), [
            'name' => 'Production',
            'color' => 'chartreuse',
        ])
        ->assertSessionHasErrors('color');
});

it('shares the environment on inertia requests', function (): void {
    $environment = Environment::factory()->create(['application_id' => $this->application->id]);

    $this->actingAs($this->user)
        ->get(route('environments.show', [$this->application->id, $environment->id]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/environments/show')
            ->where('environment.name', $environment->name)
            ->where('application.name', $this->application->name)
        );
});

it('never updates the slug', function (): void {
    $environment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'slug' => 'production',
    ]);

    $this->actingAs($this->user)
        ->put(route('environments.update', [$this->application->id, $environment->id]), [
            'name' => 'Staging',
            'slug' => 'staging',
        ])
        ->assertSessionHasNoErrors();

    expect($environment->fresh()->slug)->toBe('production')
        ->and($environment->fresh()->name)->toBe('Staging');
});

it('rejects a primary component from another environment', function (): void {
    $environment = Environment::factory()->create(['application_id' => $this->application->id]);
    $otherEnvironment = Environment::factory()->create(['application_id' => $this->application->id]);
    $foreign = Component::factory()->create([
        'environment_id' => $otherEnvironment->id,
        'server_id' => $this->server->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('environments.update', [$this->application->id, $environment->id]), [
            'name' => $environment->name,
            'primary_component_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('primary_component_id');
});

it('404s when the environment belongs to another application', function (): void {
    $otherApplication = Application::factory()->create(['project_id' => $this->user->current_project_id]);
    $environment = Environment::factory()->create(['application_id' => $otherApplication->id]);

    $this->actingAs($this->user)
        ->get(route('environments.show', [$this->application->id, $environment->id]))
        ->assertNotFound();
});

it('denies creating an environment in another project', function (): void {
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $foreignApplication = Application::factory()->create(['project_id' => $other->current_project_id]);

    $this->actingAs($this->user)
        ->post(route('environments.store', $foreignApplication->id), ['name' => 'Production'])
        ->assertForbidden();
});
