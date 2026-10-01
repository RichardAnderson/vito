<?php

use App\Actions\Application\DeleteApplication;
use App\Actions\Component\CreateComponent;
use App\Actions\Component\DeleteComponent;
use App\Actions\Environment\DeleteEnvironment;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ComponentType;
use App\Enums\EnvironmentStatus;
use App\Facades\SSH;
use App\Jobs\Component\DeleteComponentJob;
use App\Models\Application;
use App\Models\Component;
use App\Models\Database;
use App\Models\Environment;
use App\Models\EnvironmentResource;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

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

function makeInstalledComponent(mixed $test, string $name = 'web', string $user = 'mdbin'): Component
{
    return app(CreateComponent::class)->create($test->environment, [
        'name' => $name,
        'server_id' => $test->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => $user,
        'php_version' => '8.2',
    ]);
}

it('removes the vhost, checkout and row', function (): void {
    $component = makeInstalledComponent($this);

    app(DeleteComponent::class)->delete($component);

    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
    SSH::assertExecutedContains('rm -rf');
    $this->assertDatabaseMissing('components', ['id' => $component->id]);
});

it('leaves an fpm pool a site still uses', function (): void {
    $component = makeInstalledComponent($this);

    Site::query()->whereKey($this->site->id)->update([
        'isolated_user_id' => $component->isolated_user_id,
        'php_version' => '8.2',
    ]);

    app(DeleteComponent::class)->delete($component);

    SSH::assertNotExecutedContains('remove-fpm-pool');
    $this->assertDatabaseHas('isolated_users', ['id' => $component->isolated_user_id]);
});

it('returns silently when the component row is already gone', function (): void {
    $component = makeInstalledComponent($this);
    $componentId = $component->id;
    $serverId = $component->server_id;
    $component->forceDelete();

    (new DeleteComponentJob($componentId, $serverId))->handle();
})->throwsNoExceptions();

it('deletes an environment through a chain that ends with the parent row', function (): void {
    $component = makeInstalledComponent($this);

    app(DeleteEnvironment::class)->delete($this->environment);

    $this->assertDatabaseMissing('components', ['id' => $component->id]);
    $this->assertDatabaseMissing('environments', ['id' => $this->environment->id]);
});

it('keeps the component row alive until its teardown job runs', function (): void {
    Bus::fake();
    $component = makeInstalledComponent($this);

    app(DeleteEnvironment::class)->delete($this->environment);

    // With the queue faked the chain has not executed, so both rows must still exist —
    // this is the ordering a cascade-then-dispatch implementation gets wrong.
    $this->assertDatabaseHas('components', ['id' => $component->id]);
    $this->assertDatabaseHas('environments', ['id' => $this->environment->id]);
    expect($this->environment->fresh()->status)->toBe(EnvironmentStatus::DELETING);

    Bus::assertChained([DeleteComponentJob::class, \App\Jobs\Environment\DeleteParentRowJob::class]);
});

it('marks the application deleting while its components are torn down', function (): void {
    Bus::fake();

    makeInstalledComponent($this);

    app(DeleteApplication::class)->delete($this->application);

    expect($this->application->fresh()->status)->toBe(ApplicationStatus::DELETING);
    $this->assertDatabaseHas('applications', ['id' => $this->application->id]);
});

it('never deletes an attached database when the application goes', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id]);
    EnvironmentResource::query()->create([
        'environment_id' => $this->environment->id,
        'resource_type' => Database::class,
        'resource_id' => $database->id,
    ]);

    makeInstalledComponent($this);

    app(DeleteApplication::class)->delete($this->application);

    $this->assertDatabaseMissing('applications', ['id' => $this->application->id]);
    $this->assertDatabaseHas('databases', ['id' => $database->id]);
});

it('leaves the component failed and the environment deleting when teardown throws', function (): void {
    $component = makeInstalledComponent($this);

    $ssh = SSH::fake();
    $ssh->execWillFail();

    // UniqueQueue::run() catches and calls $this->fail(), so the job must go through the
    // queue for failed() to fire — a hand-constructed instance has no job to fail.
    dispatch(new DeleteComponentJob($component->id, $component->server_id));

    expect($component->fresh()->status)->toBe(ComponentStatus::FAILED);
    $this->assertDatabaseHas('components', ['id' => $component->id]);
});
