<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('lists applications', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);

    $this->actingAs($this->user)
        ->get(route('applications'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('applications/index'));

    expect($application->fresh())->not->toBeNull();
});

it('creates an application', function (): void {
    $this->actingAs($this->user)
        ->post(route('applications.store'), [
            'name' => 'mdbin',
            'repository' => 'organization/mdbin',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('applications', [
        'name' => 'mdbin',
        'project_id' => $this->user->current_project_id,
    ]);
});

it('creates a production environment when a production branch is given', function (): void {
    $this->actingAs($this->user)
        ->post(route('applications.store'), [
            'name' => 'mdbin',
            'repository' => 'organization/mdbin',
            'production_branch' => 'main',
        ])
        ->assertSessionHasNoErrors();

    $application = Application::query()->where('name', 'mdbin')->firstOrFail();

    $this->assertDatabaseHas('environments', [
        'application_id' => $application->id,
        'name' => 'Production',
        'slug' => 'production',
        'branch' => 'main',
    ]);
});

it('creates no environment when the production branch is omitted', function (): void {
    $this->actingAs($this->user)
        ->post(route('applications.store'), [
            'name' => 'mdbin',
            'repository' => 'organization/mdbin',
        ])
        ->assertSessionHasNoErrors();

    $application = Application::query()->where('name', 'mdbin')->firstOrFail();

    expect($application->environments()->count())->toBe(0);
});

it('rejects a duplicate application name in the same project', function (): void {
    Application::factory()->create([
        'project_id' => $this->user->current_project_id,
        'name' => 'mdbin',
    ]);

    $this->actingAs($this->user)
        ->post(route('applications.store'), ['name' => 'mdbin'])
        ->assertSessionHasErrors('name');
});

it('allows the same application name in a different project', function (): void {
    Application::factory()->create([
        'project_id' => $this->user->current_project_id,
        'name' => 'mdbin',
    ]);

    $other = User::factory()->create();
    $other->ensureHasDefaultProject();

    $this->actingAs($other)
        ->post(route('applications.store'), ['name' => 'mdbin'])
        ->assertSessionHasNoErrors();

    expect(Application::query()->where('name', 'mdbin')->count())->toBe(2);
});

it('requires a repository when a source control is given', function (): void {
    $this->actingAs($this->user)
        ->post(route('applications.store'), [
            'name' => 'mdbin',
            'source_control_id' => 999999,
        ])
        ->assertSessionHasErrors(['source_control_id', 'repository']);
});

it('shows an application with its environments', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);

    $this->actingAs($this->user)
        ->get(route('applications.show', $application->id))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/show')
            ->where('application.name', $application->name)
        );
});

it('updates an application', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);

    $this->actingAs($this->user)
        ->put(route('applications.update', $application->id), [
            'name' => 'renamed',
            'repository' => 'organization/renamed',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('applications', ['id' => $application->id, 'name' => 'renamed']);
});

it('deletes an application through the danger zone', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);

    $this->actingAs($this->user)
        ->delete(route('applications.destroy', $application->id))
        ->assertRedirect(route('applications'));

    $this->assertDatabaseMissing('applications', ['id' => $application->id]);
});

it('exposes the application status to the page', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);

    $this->actingAs($this->user)
        ->get(route('applications.show', $application->id))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('application.status', 'ready')
            ->where('application.status_color', 'success')
            ->etc()
        );
});

it('denies access to an application in another project', function (): void {
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $application = Application::factory()->create(['project_id' => $other->current_project_id]);

    $this->actingAs($this->user)
        ->get(route('applications.show', $application->id))
        ->assertForbidden();
});
