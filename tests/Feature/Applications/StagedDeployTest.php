<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Environment\AdvanceEnvironmentDeployment;
use App\Actions\Environment\DeployEnvironment;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Enums\DeploymentStage;
use App\Enums\DeploymentStatus;
use App\Enums\EnvironmentDeploymentStatus;
use App\Enums\EnvironmentStatus;
use App\Facades\SSH;
use App\Jobs\Environment\DeployComponentStageJob;
use App\Models\Application;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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
        'branch' => 'main',
    ]);
    foreach (['build', 'pre-flight'] as $name) {
        $this->environment->deploymentScripts()->create(['name' => $name, 'content' => '']);
    }
    $this->web = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);
});

function secondServer(mixed $test): Server
{
    $server = Server::factory()->create([
        'user_id' => $test->user->id,
        'project_id' => $test->user->current_project_id,
    ]);
    $server->services()->create(['type' => 'process_manager', 'name' => 'supervisor', 'version' => 'latest', 'status' => 'ready']);
    $server->services()->create(['type' => 'webserver', 'name' => 'nginx', 'version' => 'latest', 'status' => 'ready']);

    return $server;
}

it('writes one deployment per component sharing one commit, with no release yet', function (): void {
    Queue::fake();
    $workers = app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => secondServer($this)->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);
    $workers->update(['status' => ComponentStatus::READY]);

    $run = app(DeployEnvironment::class)->run($this->environment->fresh());

    $deployments = Deployment::query()->where('environment_deployment_id', $run->id)->get();

    expect($deployments)->toHaveCount(2)
        ->and($deployments->pluck('release')->unique()->all())->toBe([null])
        ->and($deployments->pluck('site_id')->unique()->all())->toBe([null])
        ->and($run->release)->not->toBeNull()
        ->and($this->environment->fresh()->status)->toBe(EnvironmentStatus::DEPLOYING);
});

it('gives a load balancer deployment no script', function (): void {
    Queue::fake();
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => secondServer($this)->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);
    $lb->update(['status' => ComponentStatus::READY]);

    $run = app(DeployEnvironment::class)->run($this->environment->fresh());

    $lbDeployment = Deployment::query()->where('component_id', $lb->id)->firstOrFail();

    expect($lbDeployment->deployment_script_id)->toBeNull();
});

it('re-derives a primary component that is a load balancer', function (): void {
    Queue::fake();
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => secondServer($this)->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);
    $lb->update(['status' => ComponentStatus::READY]);
    $this->environment->update(['primary_component_id' => $lb->id]);

    app(DeployEnvironment::class)->run($this->environment->fresh());

    expect($this->environment->fresh()->primary_component_id)->toBe($this->web->id);
});

it('holds the build stage until every clone has reported', function (): void {
    Queue::fake();
    $workers = app(CreateComponent::class)->create($this->environment, [
        'name' => 'workers',
        'server_id' => secondServer($this)->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::PROCESSES],
        'user' => 'mdbin',
    ]);
    $workers->update(['status' => ComponentStatus::READY]);

    $run = app(DeployEnvironment::class)->run($this->environment->fresh());
    $deployments = Deployment::query()->where('environment_deployment_id', $run->id)->get();

    app(AdvanceEnvironmentDeployment::class)->componentFinished($deployments[0], DeploymentStage::CLONE);

    expect($run->fresh()->current_stage)->toBe(DeploymentStage::CLONE);

    app(AdvanceEnvironmentDeployment::class)->componentFinished($deployments[1], DeploymentStage::CLONE);

    expect($run->fresh()->current_stage)->toBe(DeploymentStage::BUILD);
});

it('advances exactly once when a component reports the same stage twice', function (): void {
    Queue::fake();
    $run = app(DeployEnvironment::class)->run($this->environment->fresh());
    $deployment = Deployment::query()->where('environment_deployment_id', $run->id)->firstOrFail();

    app(AdvanceEnvironmentDeployment::class)->componentFinished($deployment, DeploymentStage::CLONE);
    app(AdvanceEnvironmentDeployment::class)->componentFinished($deployment, DeploymentStage::CLONE);

    expect($run->fresh()->current_stage)->toBe(DeploymentStage::BUILD);

    Queue::assertPushed(DeployComponentStageJob::class, function (DeployComponentStageJob $job): bool {
        return true;
    });
});

it('stops the fan-out when a clone fails and never fails the environment', function (): void {
    Queue::fake();
    $run = app(DeployEnvironment::class)->run($this->environment->fresh());
    $deployment = Deployment::query()->where('environment_deployment_id', $run->id)->firstOrFail();

    app(AdvanceEnvironmentDeployment::class)->componentFailed($deployment, 'clone failed');

    expect($run->fresh()->status)->toBe(EnvironmentDeploymentStatus::FAILED)
        ->and($deployment->fresh()->status)->toBe(DeploymentStatus::FAILED)
        ->and($this->environment->fresh()->status)->toBe(EnvironmentStatus::FAILED);
});

it('marks a release-stage failure partial rather than failed', function (): void {
    Queue::fake();
    $run = app(DeployEnvironment::class)->run($this->environment->fresh());
    $run->update(['current_stage' => DeploymentStage::RELEASE]);
    $deployment = Deployment::query()->where('environment_deployment_id', $run->id)->firstOrFail();

    app(AdvanceEnvironmentDeployment::class)->componentFailed($deployment, 'release failed');

    expect($run->fresh()->status)->toBe(EnvironmentDeploymentStatus::PARTIAL)
        // PARTIAL surfaces the drift on the run; the environment itself stays usable.
        ->and($this->environment->fresh()->status)->toBe(EnvironmentStatus::READY);
});

it('runs all four stages in order on the sync connection', function (): void {
    $run = app(DeployEnvironment::class)->run($this->environment->fresh());

    expect($run->fresh()->status)->toBe(EnvironmentDeploymentStatus::FINISHED);

    $deployment = Deployment::query()->where('environment_deployment_id', $run->id)->firstOrFail();

    expect($deployment->current_stage)->toBe(DeploymentStage::RELEASE)
        ->and($deployment->release)->toBe($run->fresh()->release)
        ->and($deployment->active)->toBeTrue()
        ->and($this->web->fresh()->needs_redeploy)->toBeFalse()
        ->and($this->environment->fresh()->status)->toBe(EnvironmentStatus::READY);

    $this->assertDatabaseHas('deployments', ['active' => true, 'component_id' => $this->web->id]);
});
