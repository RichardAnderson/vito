<?php

use App\Models\Application;
use App\Models\Component;
use App\Models\CronJob;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `CronJob::crontab()` filters on (server, linux user) with no `site_id` predicate, so
 * scheduler entries from a Site and a Component union into one crontab. These assertions
 * exist so that predicate is not "fixed" into existence later.
 */
it('renders site and component cron jobs into one crontab for the same server and user', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);
    $environment = Environment::factory()->create(['application_id' => $application->id]);
    $component = Component::factory()->create([
        'environment_id' => $environment->id,
        'server_id' => $this->server->id,
    ]);

    CronJob::factory()->create([
        'server_id' => $this->server->id,
        'site_id' => $this->site->id,
        'user' => 'vito',
        'command' => 'php site-artisan schedule:run',
    ]);

    CronJob::factory()->create([
        'server_id' => $this->server->id,
        'component_id' => $component->id,
        'user' => 'vito',
        'command' => 'php component-artisan schedule:run',
    ]);

    $crontab = CronJob::crontab($this->server, 'vito');

    expect($crontab)->toContain('site-artisan')
        ->and($crontab)->toContain('component-artisan');
});

it('leaves the site cron job intact when the component job is deleted', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);
    $environment = Environment::factory()->create(['application_id' => $application->id]);
    $component = Component::factory()->create([
        'environment_id' => $environment->id,
        'server_id' => $this->server->id,
    ]);

    CronJob::factory()->create([
        'server_id' => $this->server->id,
        'site_id' => $this->site->id,
        'user' => 'vito',
        'command' => 'php site-artisan schedule:run',
    ]);

    $componentJob = CronJob::factory()->create([
        'server_id' => $this->server->id,
        'component_id' => $component->id,
        'user' => 'vito',
        'command' => 'php component-artisan schedule:run',
    ]);

    $componentJob->delete();

    $crontab = CronJob::crontab($this->server, 'vito');

    expect($crontab)->toContain('site-artisan')
        ->and($crontab)->not->toContain('component-artisan');
});

it('cascades component cron jobs when the component is deleted', function (): void {
    $application = Application::factory()->create(['project_id' => $this->user->current_project_id]);
    $environment = Environment::factory()->create(['application_id' => $application->id]);
    $component = Component::factory()->create([
        'environment_id' => $environment->id,
        'server_id' => $this->server->id,
    ]);

    $componentJob = CronJob::factory()->create([
        'server_id' => $this->server->id,
        'component_id' => $component->id,
        'user' => 'vito',
    ]);

    $component->delete();

    $this->assertDatabaseMissing('cron_jobs', ['id' => $componentJob->id]);
});
