<?php

use App\Actions\Component\CreateComponent;
use App\Actions\EnvironmentDomain\CreateEnvironmentDomain;
use App\Actions\EnvironmentDomain\DeleteEnvironmentDomain;
use App\Actions\SSL\DeleteSsl;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\SslStatus;
use App\Facades\SSH;
use App\Jobs\SSL\CreateLetsEncryptWildcardSslJob;
use App\Models\Application;
use App\Models\Component;
use App\Models\DNSProvider;
use App\Models\Domain;
use App\Models\Environment;
use App\Models\Ssl;
use App\Services\Webserver\Caddy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
    $this->component = app(CreateComponent::class)->create($this->environment, [
        'name' => 'web',
        'server_id' => $this->server->id,
        'type' => ComponentType::APP->value,
        'functions' => [ComponentFunctions::HTTP],
        'user' => 'mdbin',
        'php_version' => '8.2',
    ]);
});

function dnsBackedZone(mixed $test, string $zone = 'mdbin.app'): Domain
{
    return Domain::factory()->create([
        'project_id' => $test->user->current_project_id,
        'domain' => $zone,
        'dns_provider_id' => DNSProvider::factory()->create([
            'project_id' => $test->user->current_project_id,
        ])->id,
    ]);
}

it('writes the http-only vhost before invoking certbot on nginx', function (): void {
    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'uat.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    $commands = collect(SSH::getExecutedCommands())->map(fn ($c): string => (string) $c);
    $vhostIndex = $commands->search(fn (string $c): bool => str_contains($c, 'sites-available/component-'.$this->component->id));
    $certbotIndex = $commands->search(fn (string $c): bool => str_contains($c, 'certbot'));

    expect($vhostIndex)->not->toBeFalse()
        ->and($certbotIndex)->not->toBeFalse()
        ->and($vhostIndex)->toBeLessThan($certbotIndex);
});

it('resolves the acme email from the project', function (): void {
    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'uat.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    SSH::assertExecutedContains('-m '.$this->user->email);
});

it('issues no certificate on caddy for a non-wildcard host', function (): void {
    $this->server->webserver()?->update(['name' => Caddy::id()]);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'uat.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    expect($domain->fresh()->ssl_id)->toBeNull()
        ->and($domain->fresh()->status)->toBe(EnvironmentDomainStatus::ACTIVE)
        ->and(Ssl::query()->count())->toBe(0);

    SSH::assertNotExecutedContains('certbot');
});

it('drives certbot for a wildcard host on caddy', function (): void {
    Bus::fake([CreateLetsEncryptWildcardSslJob::class]);
    $this->server->webserver()?->update(['name' => Caddy::id()]);
    dnsBackedZone($this);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => '*.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    expect($domain->fresh()->ssl_id)->not->toBeNull()
        ->and($domain->fresh()->status)->toBe(EnvironmentDomainStatus::CREATING);

    Bus::assertChained([
        CreateLetsEncryptWildcardSslJob::class,
        \App\Jobs\EnvironmentDomain\FinaliseDomainCertificateJob::class,
    ]);
});

it('leaves a wildcard domain creating until the certificate lands', function (): void {
    Bus::fake([CreateLetsEncryptWildcardSslJob::class]);
    dnsBackedZone($this);

    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => '*.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::CREATING);

    // The finaliser flips it only once the Ssl reports CREATED.
    $ssl = $domain->fresh()->ssl;
    $ssl->update([
        'status' => SslStatus::CREATED,
        'certificate_path' => '/etc/letsencrypt/live/'.$ssl->id.'/fullchain.pem',
        'pk_path' => '/etc/letsencrypt/live/'.$ssl->id.'/privkey.pem',
    ]);

    (new \App\Jobs\EnvironmentDomain\FinaliseDomainCertificateJob($domain->fresh(), $this->server->id))->handle();

    expect($domain->fresh()->status)->toBe(EnvironmentDomainStatus::ACTIVE);

    // The certificate path travels in the uploaded vhost body, not in an exec command.
    $vhost = app(\App\Actions\Component\GenerateComponentNginxVhost::class)->generate($this->component->fresh());
    expect($vhost)->toContain('/etc/letsencrypt/live/'.$ssl->id);
});

it('does not touch another domain certificate when a second domain is added', function (): void {
    $first = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'one.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);
    $firstSslId = $first->fresh()->ssl_id;

    app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'two.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    expect($first->fresh()->ssl_id)->toBe($firstSslId);
});

it('removes the host from the vhost when the domain is deleted', function (): void {
    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'gone.mdbin.app',
        'ssl_method' => 'none',
    ]);

    app(DeleteEnvironmentDomain::class)->delete($domain);

    $this->assertDatabaseMissing('environment_domains', ['id' => $domain->id]);

    $vhost = app(\App\Actions\Component\GenerateComponentNginxVhost::class)->generate($this->component->fresh());
    expect($vhost)->not->toContain('gone.mdbin.app');
});

it('refuses to delete a domain referenced certificate and hides it from the server ssl list', function (): void {
    $domain = app(CreateEnvironmentDomain::class)->create($this->environment, [
        'host' => 'uat.mdbin.app',
        'ssl_method' => 'letsencrypt',
    ]);

    $ssl = $domain->fresh()->ssl;

    expect(fn () => app(DeleteSsl::class)->delete($ssl))->toThrow(ValidationException::class);

    $ssl->update(['status' => SslStatus::CREATED, 'certificate_path' => '/a', 'pk_path' => '/b']);
    expect(Ssl::activeServerLevel($this->server->id)->pluck('id'))->not->toContain($ssl->id);
});
