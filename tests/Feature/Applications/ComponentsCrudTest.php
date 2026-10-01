<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Component\UpdateComponent;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Component;
use App\Models\Environment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    SSH::fake();
    $this->application = Application::factory()->create(['project_id' => $this->user->current_project_id]);
    $this->environment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'name' => 'Production',
        'slug' => 'production',
    ]);
});

it('creates a component and nominates it as primary', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);

    expect($component->path)->toBe('/home/mdbin/app-'.$this->application->id.'-production')
        ->and($component->isolatedUser?->username)->toBe('mdbin')
        ->and($this->environment->fresh()->primary_component_id)->toBe($component->id);

    $this->assertDatabaseHas('component_functions', [
        'component_id' => $component->id,
        'function' => ComponentFunctions::HTTP,
    ]);
});

it('does not nominate a load balancer as primary', function (): void {
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $this->server->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    expect($lb->type)->toBe(ComponentType::LOAD_BALANCER)
        ->and($this->environment->fresh()->primary_component_id)->toBeNull();
});

it('rejects a second scheduler in the same environment', function (): void {
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::SCHEDULER],
        'user' => 'mdbin',
    ]);

    $second = Server::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $this->user->current_project_id,
    ]);

    expect(fn () => app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers-2',
        'server_id' => $second->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::SCHEDULER],
        'user' => 'mdbin',
    ]))->toThrow(ValidationException::class);
});

it('rejects a second component on the same server and user', function (): void {
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);

    expect(fn () => app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]))->toThrow(ValidationException::class);
});

it('allows the same server with a different user', function (): void {
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);

    $second = app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin-workers',
    ]);

    expect($second->id)->not->toBeNull();
});

it('rejects a server from another project', function (): void {
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $foreignServer = Server::factory()->create([
        'user_id' => $other->id,
        'project_id' => $other->current_project_id,
    ]);

    expect(fn () => app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $foreignServer->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]))->toThrow(ValidationException::class);
});

it('requires at least one function on an app component', function (): void {
    expect(fn () => app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [],
        'user' => 'mdbin',
    ]))->toThrow(ValidationException::class);
});

it('rejects a reserved linux user name', function (): void {
    expect(fn () => app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'root',
    ]))->toThrow(ValidationException::class);
});

it('syncs functions on update', function (): void {
    $component = Component::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
        'status' => ComponentStatus::READY,
    ]);
    $component->functions()->create(['function' => ComponentFunctions::HTTP, 'status' => ComponentStatus::READY]);

    app(UpdateComponent::class)->update($component, [
        'name' => 'renamed',
        'functions' => [ComponentFunctions::HTTP, ComponentFunctions::PROCESSES],
    ]);

    expect($component->fresh()->name)->toBe('renamed');
    $this->assertDatabaseHas('component_functions', [
        'component_id' => $component->id,
        'function' => ComponentFunctions::PROCESSES,
    ]);
    expect($component->fresh()->functions)->toHaveCount(2);
});

it('removes a function that is no longer requested', function (): void {
    $component = Component::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
    ]);
    $component->functions()->create(['function' => ComponentFunctions::HTTP, 'status' => ComponentStatus::READY]);
    $component->functions()->create(['function' => ComponentFunctions::PROCESSES, 'status' => ComponentStatus::READY]);

    app(UpdateComponent::class)->update($component, [
        'name' => $component->name,
        'functions' => [ComponentFunctions::HTTP],
    ]);

    $this->assertDatabaseMissing('component_functions', [
        'component_id' => $component->id,
        'function' => ComponentFunctions::PROCESSES,
    ]);
});

it('renders the component settings page with its functions', function (): void {
    $component = Component::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
        'status' => ComponentStatus::READY,
    ]);
    $component->functions()->create(['function' => ComponentFunctions::PROCESSES, 'status' => ComponentStatus::READY]);

    $this->actingAs($this->user)
        ->get(route('components.settings', [
            'application' => $this->application->id,
            'environment' => $this->environment->id,
            'component' => $component->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/environments/component-settings')
            ->where('component.id', $component->id)
            ->where('component.functions.0.function', ComponentFunctions::PROCESSES)
            ->etc()
        );
});
