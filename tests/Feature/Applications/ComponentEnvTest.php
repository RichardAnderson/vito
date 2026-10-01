<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Component\WriteComponentEnv;
use App\Actions\Environment\UpsertEnvironmentVariable;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Environment;
use App\Models\ServerLog;
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
        'slug' => 'production',
    ]);
    $this->component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
    ]);
});

it('writes the resolved variables into the release env file', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);

    app(WriteComponentEnv::class)->write($this->component->fresh(), '/home/mdbin/app-1-production/releases/x');

    SSH::assertExecutedContains('/home/mdbin/app-1-production/releases/x/.env');
});

/**
 * P5: OS::write sends the payload over SFTP, so only the chmod/mv/chown reach a ServerLog.
 */
it('never lands a secret value in a server log', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_KEY',
        'value' => 'base64:super-secret-value',
        'is_secret' => true,
    ]);

    app(WriteComponentEnv::class)->write($this->component->fresh());

    foreach (ServerLog::query()->get() as $log) {
        expect((string) $log->name)->not->toContain('super-secret-value');
    }

    $commands = collect(SSH::getExecutedCommands())->map(fn ($command): string => (string) $command)->implode("\n");

    expect($commands)->not->toContain('super-secret-value');
});

it('writes nothing when the environment has no variables', function (): void {
    app(WriteComponentEnv::class)->write($this->component->fresh());

    SSH::assertNotExecutedContains('.env');
});
