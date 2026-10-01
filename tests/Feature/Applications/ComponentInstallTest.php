<?php

use App\Actions\Component\CreateComponent;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'name' => 'Production',
        'slug' => 'production',
        'branch' => 'main',
    ]);
});

it('installs an http component with an id-keyed checkout and vhost', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);

    expect($component->fresh()->status)->toBe(ComponentStatus::READY);

    SSH::assertExecutedContains('app-'.$this->application->id.'-production');
    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
});

/**
 * The processes function unlocks workers on the component; it no longer installs a command of
 * its own, so nothing is provisioned until the operator adds a worker.
 */
it('creates no worker on its own for the processes function', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);

    expect($component->fresh()->status)->toBe(ComponentStatus::READY);
    $this->assertDatabaseMissing('workers', ['component_id' => $component->id]);
});

/**
 * The regression for the nested-dispatch trap: CreateWorker would dispatch Worker\CreateJob on
 * the server key InstallComponentJob already holds, so under `sync` the Supervisor program is
 * silently never written.
 */
it('writes the supervisor program for a worker added to a component', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);

    $worker = app(\App\Actions\Worker\CreateWorker::class)->create($this->server, [
        'name' => 'queue',
        'command' => 'php artisan queue:work',
        'user' => 'mdbin',
        'auto_start' => true,
        'auto_restart' => true,
        'numprocs' => 1,
    ], null, $component);

    SSH::assertExecutedContains('/etc/supervisor/conf.d/'.$worker->id.'.conf');
});

it('creates a component-owned cron job for the scheduler function', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'scheduler',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::SCHEDULER],
        'user' => 'mdbin',
    ]);

    $this->assertDatabaseHas('cron_jobs', [
        'component_id' => $component->id,
        'site_id' => null,
        'frequency' => '* * * * *',
    ]);

    SSH::assertExecutedContains('schedule:run');
});

it('gives a load balancer a vhost without a checkout', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $this->server->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    expect($component->fresh()->status)->toBe(ComponentStatus::READY);

    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
    SSH::assertNotExecutedContains('releases');
});

/**
 * Without the FPM pool step every PHP http component would 502: the vhost's fastcgi_pass
 * points at a socket nothing creates. Under SSH::fake() the existence probe always succeeds,
 * so this asserts the guard runs and the pool is therefore not rewritten.
 */
it('probes for an existing fpm pool before creating one', function (): void {
    $component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);

    expect($component->fresh()->status)->toBe(ComponentStatus::READY);

    SSH::assertExecutedContains('test -f /etc/php/8.2/fpm/pool.d/mdbin.conf');
    SSH::assertNotExecutedContains('sudo mv /tmp/', 'The FPM pool was rewritten even though the probe found it.');
});
