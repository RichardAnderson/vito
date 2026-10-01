<?php

use App\Actions\Component\CreateComponent;
use App\Actions\EnvironmentDomain\CreateEnvironmentDomain;
use App\Actions\Site\CreateSite;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Component;
use App\Models\Domain;
use App\Models\Environment;
use App\Models\Server;
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

function makeHttpComponent(mixed $test, string $name = 'web', string $user = 'mdbin', ?Server $server = null): Component
{
    return app(CreateComponent::class)->create($test->environment, [
        'name' => $name,
        'server_id' => ($server ?? $test->server)->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => $user,
        'php_version' => '8.2',
    ]);
}

it('creates a domain served by the environment primary component', function (): void {
    $component = makeHttpComponent($this);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'uat.mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect($domain->host)->toBe('uat.mdbin.app')
        ->and($domain->terminatingComponent()?->id)->toBe($component->id);

    $this->assertDatabaseHas('environment_domains', ['id' => $domain->id, 'environment_id' => $this->environment->id]);
});

it('serves a domain from a load balancer when that is the only http component', function (): void {
    $lb = app(CreateComponent::class)->create($this->environment, [
        'name' => 'edge',
        'server_id' => $this->server->id,
        'type' => ComponentType::LOAD_BALANCER->value,
        'user' => 'edge',
    ]);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect($domain->terminatingComponent()?->id)->toBe($lb->id);
});

it('rejects a duplicate host in the same environment', function (): void {
    makeHttpComponent($this);

    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('creates a redirect domain pointing at an alias', function (): void {
    makeHttpComponent($this);

    $alias = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    $redirect = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $alias->id,
        'ssl_method' => 'none',
    ]);

    expect($redirect->type)->toBe(EnvironmentDomainType::REDIRECT)
        ->and($redirect->redirectTo->host)->toBe('mdbin.app');
});

it('defaults a new domain to the alias type', function (): void {
    makeHttpComponent($this);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect($domain->type)->toBe(EnvironmentDomainType::ALIAS)
        ->and($domain->redirect_to_domain_id)->toBeNull();
});

it('rejects a redirect with no alias chosen', function (): void {
    makeHttpComponent($this);

    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('rejects a redirect pointing at another redirect', function (): void {
    makeHttpComponent($this);

    $alias = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);
    $redirect = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $alias->id,
        'ssl_method' => 'none',
    ]);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'old.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $redirect->id,
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('rejects an alias from another environment as a redirect target', function (): void {
    makeHttpComponent($this);

    $otherEnvironment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'slug' => 'staging',
    ]);
    $foreignAlias = \App\Models\EnvironmentDomain::factory()->create([
        'environment_id' => $otherEnvironment->id,
        'host' => 'staging.mdbin.app',
    ]);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $foreignAlias->id,
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('refuses to delete an alias a redirect still points at', function (): void {
    makeHttpComponent($this);

    $alias = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);
    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $alias->id,
        'ssl_method' => 'none',
    ]);

    expect(fn () => app(\App\Actions\EnvironmentDomain\DeleteEnvironmentDomain::class)->delete($alias))
        ->toThrow(ValidationException::class);
});

it('rejects a cross environment collision on the same server', function (): void {
    makeHttpComponent($this);

    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    $otherEnvironment = Environment::factory()->create([
        'application_id' => $this->application->id,
        'slug' => 'staging',
    ]);
    app(CreateComponent::class)->create($otherEnvironment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'staging',
    ]);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($otherEnvironment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('rejects a host a site on that server already answers on', function (): void {
    makeHttpComponent($this);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => $this->site->domain,
        'ssl_method' => 'none',
    ]))->toThrow(ValidationException::class);
});

it('parks a domain as pending when the environment has no component yet', function (): void {
    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::PENDING)
        ->and($domain->terminatingComponent())->toBeNull();
});

it('allows a redirect to an alias before any component exists', function (): void {
    $alias = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    $redirect = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT->value,
        'redirect_to_domain_id' => $alias->id,
        'ssl_method' => 'none',
    ]);

    expect($redirect->fresh()->status)->toBe(EnvironmentDomainStatus::PENDING);
});

it('activates pending domains and writes their vhost once a component is installed', function (): void {
    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'mdbin.app',
        'ssl_method' => 'none',
    ]);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::PENDING);

    $component = makeHttpComponent($this);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::ACTIVE)
        ->and($domain->fresh()->terminatingComponent()?->id)->toBe($component->id);

    $vhost = app(\App\Actions\Component\GenerateComponentNginxVhost::class)->generate($component->fresh());
    expect($vhost)->toContain('server_name mdbin.app;');
});

it('fails a pending domain whose host the new server already answers on', function (): void {
    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => $this->site->domain,
        'ssl_method' => 'none',
    ]);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::PENDING);

    $component = makeHttpComponent($this);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::FAILED);

    $vhost = app(\App\Actions\Component\GenerateComponentNginxVhost::class)->generate($component->fresh());
    expect($vhost)->not->toContain('server_name '.$this->site->domain.';');
});

it('rejects a wildcard host with no dns provider', function (): void {
    makeHttpComponent($this);

    expect(fn () => app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => '*.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]))->toThrow(ValidationException::class);
});

it('accepts a wildcard host when the zone has a dns provider', function (): void {
    makeHttpComponent($this);
    Domain::factory()->create([
        'project_id' => $this->user->current_project_id,
        'domain' => 'mdbin.app',
        'dns_provider_id' => \App\Models\DNSProvider::factory()->create([
            'project_id' => $this->user->current_project_id,
        ])->id,
    ]);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => '*.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    expect($domain->isWildcard())->toBeTrue();
});

/**
 * NF1: extending the shared DomainRule instead of adding a sibling would let a wildcard into a
 * Site's on-disk path and vhost filename.
 */
it('still rejects a wildcard domain when creating a site', function (): void {
    expect(fn () => app(CreateSite::class)->create($this->server, [
        'type' => 'php-blank',
        'domain' => '*.example.com',
        'user' => 'example',
        'php_version' => '8.2',
        'web_directory' => 'public',
    ]))->toThrow(ValidationException::class);
});
