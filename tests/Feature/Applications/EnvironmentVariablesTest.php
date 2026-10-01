<?php

use App\Actions\Component\CreateComponent;
use App\Actions\Environment\ResolveComponentVariables;
use App\Actions\Environment\UpsertEnvironmentVariable;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

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

it('rejects a duplicate environment level key', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);

    expect(fn () => app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'staging']))
        ->toThrow(ValidationException::class);
});

it('allows the same key at environment and component scope', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);

    $override = app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_ENV',
        'value' => 'override',
        'component_id' => $this->component->id,
    ]);

    expect($override->component_id)->toBe($this->component->id);
});

it('rejects a key that is not a shell identifier', function (): void {
    expect(fn () => app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => '9BAD-KEY', 'value' => 'x']))
        ->toThrow(ValidationException::class);
});

it('resolves a component override above the environment value', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_ENV',
        'value' => 'override',
        'component_id' => $this->component->id,
    ]);

    $resolved = app(ResolveComponentVariables::class)->resolve($this->component->fresh());

    expect($resolved['APP_ENV']['value'])->toBe('override')
        ->and($resolved['APP_ENV']['source'])->toBe('component:web');
});

it('keeps the stored secret when an empty value is submitted', function (): void {
    $variable = app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_KEY',
        'value' => 'super-secret',
        'is_secret' => true,
    ]);

    app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_KEY',
        'value' => '',
        'is_secret' => true,
    ], $variable);

    expect($variable->fresh()->value)->toBe('super-secret');
});

it('marks affected components for redeploy', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);

    $this->assertDatabaseHas('components', ['id' => $this->component->id, 'needs_redeploy' => true]);
});

it('masks a secret in the inertia payload and shows a non secret', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, ['key' => 'APP_ENV', 'value' => 'production']);
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_KEY',
        'value' => 'super-secret',
        'is_secret' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('environments.variables', [$this->application->id, $this->environment->id]))
        ->assertSuccessful()
        ->assertInertia(function (AssertableInertia $page): void {
            $variables = collect($page->toArray()['props']['variables']);

            expect($variables->firstWhere('key', 'APP_ENV')['value'])->toBe('production')
                ->and($variables->firstWhere('key', 'APP_KEY')['value'])->toBe('••••••••');
        });

    $response = $this->actingAs($this->user)
        ->get(route('environments.variables', [$this->application->id, $this->environment->id]));

    expect($response->getContent())->not->toContain('super-secret');
});

it('stores the value encrypted at rest', function (): void {
    app(UpsertEnvironmentVariable::class)->upsert($this->environment, [
        'key' => 'APP_KEY',
        'value' => 'super-secret',
        'is_secret' => true,
    ]);

    $raw = DB::table('environment_variables')->where('key', 'APP_KEY')->value('value');

    expect($raw)->not->toContain('super-secret')
        ->and(EnvironmentVariable::query()->where('key', 'APP_KEY')->first()->value)->toBe('super-secret');
});
