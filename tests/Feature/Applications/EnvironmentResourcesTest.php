<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Component\UpdateComponentBackends;
use App\Actions\Environment\AttachEnvironmentResource;
use App\Actions\Environment\CheckEnvironmentNetworks;
use App\Actions\Environment\DeleteEnvironment;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Database;
use App\Models\Environment;
use App\Models\Network;
use App\Models\NetworkServer;
use App\Models\Server;
use App\Models\Service;
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
});

it('attaches a database and leaves it alone when the environment is deleted', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id]);

    app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);

    app(AttachEnvironmentResource::class)->attach($this->environment, [
        'resource_type' => 'database',
        'resource_id' => $database->id,
    ]);

    $this->assertDatabaseHas('environment_resources', ['environment_id' => $this->environment->id]);

    app(DeleteEnvironment::class)->delete($this->environment);

    $this->assertDatabaseMissing('environments', ['id' => $this->environment->id]);
    $this->assertDatabaseHas('databases', ['id' => $database->id]);
});

it('attaches a redis service on a chosen server', function (): void {
    $redis = Service::factory()->create([
        'server_id' => $this->server->id,
        'type' => 'memory_database',
        'name' => 'redis',
        'version' => '7.0',
    ]);

    $attached = app(AttachEnvironmentResource::class)->attach($this->environment, [
        'resource_type' => 'redis',
        'resource_id' => $redis->id,
    ]);

    expect($attached->resource_type)->toBe(Service::class)
        ->and($attached->resource_id)->toBe($redis->id);

    $this->assertDatabaseHas('environment_resources', [
        'environment_id' => $this->environment->id,
        'resource_type' => Service::class,
        'resource_id' => $redis->id,
    ]);
});

it('refuses to attach a non memory-database service as redis', function (): void {
    $webserver = Service::factory()->create([
        'server_id' => $this->server->id,
        'type' => 'webserver',
        'name' => 'nginx',
    ]);

    expect(fn () => app(AttachEnvironmentResource::class)->attach($this->environment, [
        'resource_type' => 'redis',
        'resource_id' => $webserver->id,
    ]))->toThrow(ValidationException::class);
});

it('rejects a redis service from another project', function (): void {
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $foreignServer = Server::factory()->create([
        'user_id' => $other->id,
        'project_id' => $other->current_project_id,
    ]);
    $redis = Service::factory()->create([
        'server_id' => $foreignServer->id,
        'type' => 'memory_database',
        'name' => 'redis',
    ]);

    expect(fn () => app(AttachEnvironmentResource::class)->attach($this->environment, [
        'resource_type' => 'redis',
        'resource_id' => $redis->id,
    ]))->toThrow(ValidationException::class);
});

it('lets one database be attached to several environments', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id]);
    $second = Environment::factory()->create(['application_id' => $this->application->id, 'slug' => 'staging']);

    app(AttachEnvironmentResource::class)->attach($this->environment, ['resource_type' => 'database', 'resource_id' => $database->id]);
    app(AttachEnvironmentResource::class)->attach($second, ['resource_type' => 'database', 'resource_id' => $database->id]);

    expect(\App\Models\EnvironmentResource::query()->where('resource_id', $database->id)->count())->toBe(2);
});

it('rejects a database from another project', function (): void {
    $other = User::factory()->create();
    $other->ensureHasDefaultProject();
    $foreignServer = Server::factory()->create(['user_id' => $other->id, 'project_id' => $other->current_project_id]);
    $database = Database::factory()->create(['server_id' => $foreignServer->id]);

    expect(fn () => app(AttachEnvironmentResource::class)->attach($this->environment, [
        'resource_type' => 'database',
        'resource_id' => $database->id,
    ]))->toThrow(ValidationException::class);
});

it('reports servers that share no network', function (): void {
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);

    $second = Server::factory()->create(['user_id' => $this->user->id, 'project_id' => $this->user->current_project_id]);
    $second->services()->create(['type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => 'ready']);
    app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $second->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);

    expect(app(CheckEnvironmentNetworks::class)->unreachableServers($this->environment->fresh()))->toHaveCount(2);

    $network = Network::factory()->create(['project_id' => $this->user->current_project_id]);
    foreach ([$this->server->id, $second->id] as $serverId) {
        NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $serverId, 'ip' => '100.64.0.'.$serverId]);
    }

    expect(app(CheckEnvironmentNetworks::class)->unreachableServers($this->environment->fresh()))->toBeEmpty();
});

it('addresses load balancer backends by their private network ip', function (): void {
    $lbServer = Server::factory()->create(['user_id' => $this->user->id, 'project_id' => $this->user->current_project_id]);
    $lbServer->services()->create(['type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => 'ready']);

    $network = Network::factory()->create(['project_id' => $this->user->current_project_id]);
    NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $lbServer->id, 'ip' => '100.64.0.1']);
    NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $this->server->id, 'ip' => '100.64.0.2']);

    $web = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $lbServer->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    app(UpdateComponentBackends::class)->update($lb, [
        'backends' => [['target_component_id' => $web->id, 'port' => 80]],
    ]);

    $vhost = app(\App\Actions\Component\GenerateComponentNginxVhost::class)->generate($lb->fresh());

    expect($vhost)->toContain('server 100.64.0.2:80')
        ->and($vhost)->not->toContain($this->server->local_ip ?? 'no-local-ip');
});

it('rejects a backend whose servers share no network', function (): void {
    $lbServer = Server::factory()->create(['user_id' => $this->user->id, 'project_id' => $this->user->current_project_id]);
    $lbServer->services()->create(['type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => 'ready']);

    $web = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $lbServer->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    expect(fn () => app(UpdateComponentBackends::class)->update($lb, [
        'backends' => [['target_component_id' => $web->id, 'port' => 80]],
    ]))->toThrow(ValidationException::class);
});

it('sets load balancer backends through the controller', function (): void {
    $lbServer = Server::factory()->create(['user_id' => $this->user->id, 'project_id' => $this->user->current_project_id]);
    $lbServer->services()->create(['type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => 'ready']);

    $network = Network::factory()->create(['project_id' => $this->user->current_project_id]);
    NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $lbServer->id, 'ip' => '100.64.1.1']);
    NetworkServer::query()->create(['network_id' => $network->id, 'server_id' => $this->server->id, 'ip' => '100.64.1.2']);

    $web = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $lbServer->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    $this->actingAs($this->user)
        ->post(route('components.backends', [$this->application->id, $this->environment->id, $lb->id]), [
            'backends' => [['target_component_id' => $web->id, 'port' => 8080]],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('component_backends', [
        'component_id' => $lb->id,
        'target_component_id' => $web->id,
        'port' => 8080,
    ]);
});
