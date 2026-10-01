<?php

use App\Actions\Environment\ProvisionEnvironmentDatabase;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Database;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
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

function variableValue(mixed $test, string $key): ?string
{
    /** @var ?EnvironmentVariable $variable */
    $variable = $test->environment->variables()->where('key', $key)->first();

    return $variable?->value;
}

it('creates a database, its user and the DB_ALIAS variables', function (): void {
    $result = app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
    ]);

    expect($result['password'])->not->toBeNull()
        ->and($result['credentials_pending'])->toBeFalse();

    $this->assertDatabaseHas('databases', ['name' => 'mdbin', 'server_id' => $this->server->id]);
    $this->assertDatabaseHas('database_users', ['username' => 'mdbin_app', 'server_id' => $this->server->id]);

    expect(variableValue($this, 'DB_PRIMARY_NAME'))->toBe('mdbin')
        ->and(variableValue($this, 'DB_PRIMARY_USER'))->toBe('mdbin_app')
        ->and(variableValue($this, 'DB_PRIMARY_PASSWORD'))->toBe($result['password']);
});

it('stores only the password as a secret', function (): void {
    app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
    ]);

    $secrets = $this->environment->variables()->pluck('is_secret', 'key');

    expect($secrets['DB_PRIMARY_PASSWORD'])->toBeTrue()
        ->and($secrets['DB_PRIMARY_NAME'])->toBeFalse()
        ->and($secrets['DB_PRIMARY_USER'])->toBeFalse();
});

it('attaches the database and its user as environment resources', function (): void {
    app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
    ]);

    expect($this->environment->resources()->count())->toBe(2);
});

it('rejects an alias already used by another database in the environment', function (): void {
    app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
    ]);

    expect(fn () => app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'other',
        'user' => 'other_app',
        'alias' => 'PRIMARY',
    ]))->toThrow(ValidationException::class);
});

it('rejects a lowercase or punctuated alias', function (): void {
    expect(fn () => app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'my-db',
    ]))->toThrow(ValidationException::class);
});

it('attaches an existing database with no credentials and flags them as pending', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id, 'name' => 'legacy']);

    $result = app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'existing',
        'resource_id' => $database->id,
        'alias' => 'LEGACY',
    ]);

    expect($result['credentials_pending'])->toBeTrue()
        ->and($result['password'])->toBeNull()
        ->and(variableValue($this, 'DB_LEGACY_NAME'))->toBe('legacy');

    $this->assertDatabaseMissing('environment_variables', ['key' => 'DB_LEGACY_USER']);
    $this->assertDatabaseMissing('environment_variables', ['key' => 'DB_LEGACY_PASSWORD']);
});

it('creates a user for an existing database when credentials are given', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id, 'name' => 'legacy']);

    $result = app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'existing',
        'resource_id' => $database->id,
        'alias' => 'LEGACY',
        'user' => 'legacy_app',
        'password' => 'sekrit-value',
    ]);

    expect($result['credentials_pending'])->toBeFalse()
        ->and(variableValue($this, 'DB_LEGACY_USER'))->toBe('legacy_app')
        ->and(variableValue($this, 'DB_LEGACY_PASSWORD'))->toBe('sekrit-value');

    $this->assertDatabaseHas('database_users', ['username' => 'legacy_app']);
});

it('attaches an existing database with no alias and writes no variables', function (): void {
    $database = Database::factory()->create(['server_id' => $this->server->id, 'name' => 'legacy']);

    $result = app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'existing',
        'resource_id' => $database->id,
    ]);

    expect($result['credentials_pending'])->toBeFalse()
        ->and($this->environment->variables()->count())->toBe(0)
        ->and($this->environment->resources()->count())->toBe(1);
});

it('rejects a collation that the chosen charset does not offer', function (): void {
    expect(fn () => app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4',
    ]))->toThrow(ValidationException::class);
});

it('rejects a charset the database service does not offer', function (): void {
    expect(fn () => app(ProvisionEnvironmentDatabase::class)->provision($this->environment, [
        'source' => 'new',
        'server_id' => $this->server->id,
        'name' => 'mdbin',
        'user' => 'mdbin_app',
        'alias' => 'PRIMARY',
        'charset' => 'klingon',
    ]))->toThrow(ValidationException::class);
});
