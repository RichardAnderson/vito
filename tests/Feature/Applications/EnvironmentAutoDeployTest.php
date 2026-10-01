<?php

use App\Actions\Component\CreateComponent;
use App\Actions\GithubApp\HandlePushWebhook;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Jobs\Environment\TriggerEnvironmentDeployFromWebhookJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\GitHook;
use App\Models\SourceControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    SSH::fake();
    Queue::fake();

    $this->sourceControl = SourceControl::factory()->create([
        'provider' => SourceControl::PROVIDER_GITHUB_APP,
        'project_id' => $this->user->current_project_id,
        'external_identifier' => '4242',
    ]);
    $this->application = Application::factory()->create([
        'project_id' => $this->user->current_project_id,
        'source_control_id' => $this->sourceControl->id,
        'repository' => 'organization/mdbin',
    ]);
    $this->environment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'slug' => 'production',
        'branch' => 'main',
        'auto_deploy' => true,
    ]);
});

it('queues a deploy from the generic git hook for a matching branch', function (): void {
    $hook = GitHook::query()->create([
        'environment_id' => $this->environment->id,
        'source_control_id' => $this->sourceControl->id,
        'secret' => Str::random(20),
        'events' => ['push'],
        'actions' => ['deploy'],
    ]);

    $this->postJson(route('api.git-hooks'), [
        'secret' => $hook->secret,
        'ref' => 'refs/heads/main',
    ])->assertSuccessful();

    Queue::assertPushed(TriggerEnvironmentDeployFromWebhookJob::class);
});

it('does not queue a deploy for a different branch', function (): void {
    $hook = GitHook::query()->create([
        'environment_id' => $this->environment->id,
        'source_control_id' => $this->sourceControl->id,
        'secret' => Str::random(20),
        'events' => ['push'],
        'actions' => ['deploy'],
    ]);

    $this->postJson(route('api.git-hooks'), [
        'secret' => $hook->secret,
        'ref' => 'refs/heads/feature',
    ])->assertSuccessful();

    Queue::assertNotPushed(TriggerEnvironmentDeployFromWebhookJob::class);
});

/**
 * The environment hook must not be destroyed by the Site null-guard on the first push.
 */
it('does not destroy an environment hook on push', function (): void {
    $hook = GitHook::query()->create([
        'environment_id' => $this->environment->id,
        'source_control_id' => $this->sourceControl->id,
        'secret' => Str::random(20),
        'events' => ['push'],
        'actions' => ['deploy'],
    ]);

    $this->postJson(route('api.git-hooks'), [
        'secret' => $hook->secret,
        'ref' => 'refs/heads/main',
    ])->assertSuccessful();

    $this->assertDatabaseHas('git_hooks', ['id' => $hook->id]);
});

it('queues a deploy from the github app webhook', function (): void {
    app(HandlePushWebhook::class)->handle([
        'installation' => ['id' => 4242],
        'repository' => ['full_name' => 'organization/mdbin'],
        'ref' => 'refs/heads/main',
    ]);

    Queue::assertPushed(TriggerEnvironmentDeployFromWebhookJob::class);
});

it('does not queue from the github app webhook for a different branch', function (): void {
    app(HandlePushWebhook::class)->handle([
        'installation' => ['id' => 4242],
        'repository' => ['full_name' => 'organization/mdbin'],
        'ref' => 'refs/heads/feature',
    ]);

    Queue::assertNotPushed(TriggerEnvironmentDeployFromWebhookJob::class);
});

it('does not queue when auto deploy is off', function (): void {
    $this->environment->update(['auto_deploy' => false]);

    app(HandlePushWebhook::class)->handle([
        'installation' => ['id' => 4242],
        'repository' => ['full_name' => 'organization/mdbin'],
        'ref' => 'refs/heads/main',
    ]);

    Queue::assertNotPushed(TriggerEnvironmentDeployFromWebhookJob::class);
});
