<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Component\MoveComponent;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Enums\ServerStatus;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Network;
use App\Models\NetworkServer;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    SSH::fake();
    $this->application = Application::factory()->create([
        'project_id' => $this->user->current_project_id,
        'source_control_id' => null,
        'repository' => null,
    ]);
    $this->environment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'slug' => 'production',
    ]);
    $this->target = Server::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $this->user->current_project_id,
        'status' => ServerStatus::READY,
    ]);
    $this->target->services()->create([
        'type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => 'ready',
    ]);
    $this->target->services()->create([
        'type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => 'ready',
    ]);
    $this->target->services()->create([
        'type' => 'php', 'name' => 'php', 'version' => '8.2', 'status' => 'ready',
    ]);
});

function movableComponent(mixed $test, array $functions = ['http']): \App\Models\Component
{
    return app(CreateComponent::class)->create($test->environment, [
        'name' => 'web',
        'server_id' => $test->server->id,
        'type' => ComponentType::APP->value,
        'functions' => $functions,
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);
}

it('moves a component and repoints its workers', function (): void {
    $component = movableComponent($this, [ComponentFunctions::HTTP, ComponentFunctions::PROCESSES]);

    app(\App\Actions\Worker\CreateWorker::class)->create($this->server, [
        'name' => 'queue',
        'command' => 'php artisan queue:work',
        'user' => 'mdbin',
        'auto_start' => true,
        'auto_restart' => true,
        'numprocs' => 1,
    ], null, $component);

    app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]);

    $this->assertDatabaseHas('components', ['id' => $component->id, 'server_id' => $this->target->id]);
    $this->assertDatabaseHas('workers', ['component_id' => $component->id, 'server_id' => $this->target->id]);
    expect($component->fresh()->status)->toBe(ComponentStatus::READY);
});

it('tears the vhost down on the origin server', function (): void {
    $component = movableComponent($this);

    app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]);

    SSH::assertExecutedContains('rm -f');
    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
});

it('rejects a target server in another project', function (): void {
    $component = movableComponent($this);
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $foreign = Server::factory()->create([
        'user_id' => $other->id,
        'project_id' => $other->current_project_id,
    ]);

    expect(fn () => app(MoveComponent::class)->move($component, ['server_id' => $foreign->id]))
        ->toThrow(ValidationException::class);
});

it('rejects a target without the php version installed', function (): void {
    $component = movableComponent($this);
    $this->target->services()->where('type', 'php')->delete();

    expect(fn () => app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]))
        ->toThrow(ValidationException::class);
});

it('rejects a target that already hosts a sibling under the same user', function (): void {
    $component = movableComponent($this);
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'other',
        'server_id' => $this->target->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);

    expect(fn () => app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]))
        ->toThrow(ValidationException::class);
});

it('rejects a target sharing no network with the other components', function (): void {
    $component = movableComponent($this);

    $sibling = Server::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $this->user->current_project_id,
        'status' => ServerStatus::READY,
    ]);
    $sibling->services()->create([
        'type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => 'ready',
    ]);
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $sibling->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);

    expect(fn () => app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]))
        ->toThrow(ValidationException::class);
});

it('accepts a target that shares a network with the other components', function (): void {
    $component = movableComponent($this);

    $sibling = Server::factory()->create([
        'user_id' => $this->user->id,
        'project_id' => $this->user->current_project_id,
        'status' => ServerStatus::READY,
    ]);
    $sibling->services()->create([
        'type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => 'ready',
    ]);
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $sibling->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);

    $network = Network::factory()->create(['project_id' => $this->user->current_project_id]);
    foreach ([$sibling->id, $this->target->id] as $serverId) {
        NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $serverId]);
    }

    app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]);

    $this->assertDatabaseHas('components', ['id' => $component->id, 'server_id' => $this->target->id]);
});

it('leaves the component on the origin when the move fails', function (): void {
    $component = movableComponent($this);
    $originServerId = $component->server_id;

    $ssh = SSH::fake();
    $ssh->execWillFail();

    app(MoveComponent::class)->move($component, ['server_id' => $this->target->id]);

    $this->assertDatabaseHas('components', ['id' => $component->id, 'server_id' => $originServerId]);
    expect($component->fresh()->status)->toBe(ComponentStatus::FAILED);
});
