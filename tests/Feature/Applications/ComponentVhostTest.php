<?php

use App\Actions\Component\GenerateComponentCaddyVhost;
use App\Actions\Component\GenerateComponentNginxVhost;
use App\Actions\Component\SyncComponentVhost;
use App\Enums\ComponentType;
use App\Facades\SSH;
use App\Models\Application;
use App\Models\Component;
use App\Models\ComponentFunction;
use App\Models\Environment;
use App\Models\IsolatedUser;
use App\Enums\EnvironmentDomainType;
use App\Models\EnvironmentDomain;
use App\Services\Webserver\Caddy;
use App\Traits\UniqueQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeComponent(mixed $test, ComponentType $type = ComponentType::APP): Component
{
    $application = Application::factory()->create(['project_id' => $test->user->current_project_id]);
    $environment = Environment::factory()->create(['application_id' => $application->id]);
    $isolatedUser = IsolatedUser::query()->firstOrCreate([
        'server_id' => $test->server->id,
        'username' => 'mdbin',
    ]);

    $component = Component::factory()->create([
        'environment_id' => $environment->id,
        'server_id' => $test->server->id,
        'isolated_user_id' => $isolatedUser->id,
        'type' => $type,
        'php_version' => $type === ComponentType::APP ? '8.4' : null,
        'web_directory' => '/public',
    ]);

    if ($type === ComponentType::APP) {
        ComponentFunction::query()->create([
            'component_id' => $component->id,
            'function' => 'http',
        ]);
    }

    return $component->fresh();
}

it('renders an id-keyed nginx vhost with the isolated user socket', function (): void {
    $component = makeComponent($this);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'uat.mdbin.app',
        'ssl_id' => null,
    ]);

    $vhost = app(GenerateComponentNginxVhost::class)->generate($component->fresh());

    expect($vhost)->toContain('access_log /var/log/nginx/component-'.$component->id.'-access.log')
        ->and($vhost)->toContain('error_log  /var/log/nginx/component-'.$component->id.'-error.log')
        ->and($vhost)->toContain('server_name uat.mdbin.app;')
        ->and($vhost)->toContain('fastcgi_pass unix:/run/php/php8.4-fpm-mdbin.sock;')
        ->and($vhost)->not->toContain('uat.mdbin.app-access.log');
});

it('renders a caddy vhost with caddy socket form', function (): void {
    $component = makeComponent($this);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'uat.mdbin.app',
        'ssl_id' => null,
    ]);

    $vhost = app(GenerateComponentCaddyVhost::class)->generate($component->fresh());

    expect($vhost)->toContain('import access_log component-'.$component->id)
        ->and($vhost)->toContain('unix//run/php/php8.4-fpm-mdbin.sock')
        ->and($vhost)->not->toContain('unix:/run/php');
});

it('renders a load balancer vhost even with no component functions', function (): void {
    $lb = makeComponent($this, ComponentType::LOAD_BALANCER);
    $backendComponent = makeComponent($this);

    expect($lb->functions)->toHaveCount(0);

    $lb->backends()->create([
        'target_component_id' => $backendComponent->id,
        'port' => 80,
    ]);

    EnvironmentDomain::factory()->create([
        'environment_id' => $lb->environment_id,
        'host' => 'mdbin.app',
        'ssl_id' => null,
    ]);

    $vhost = app(GenerateComponentNginxVhost::class)->generate($lb->fresh());

    expect($vhost)->toContain('upstream component_'.$lb->id.'_backend')
        ->and($vhost)->toContain('proxy_pass http://component_'.$lb->id.'_backend')
        ->and($vhost)->toContain('server_name mdbin.app;');
});

it('renders a redirect domain forwarding to the alias it points at', function (): void {
    $component = makeComponent($this);
    $alias = EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'mdbin.app',
        'ssl_id' => null,
    ]);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT,
        'redirect_to_domain_id' => $alias->id,
        'ssl_id' => null,
    ]);

    $vhost = app(GenerateComponentNginxVhost::class)->generate($component->fresh());

    expect($vhost)->toContain('server_name www.mdbin.app;')
        ->and($vhost)->toContain('return 301 http://mdbin.app$request_uri;');
});

it('keeps a redirect domain out of the served server_name list', function (): void {
    $component = makeComponent($this);
    $alias = EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'mdbin.app',
        'ssl_id' => null,
    ]);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'host' => 'www.mdbin.app',
        'type' => EnvironmentDomainType::REDIRECT,
        'redirect_to_domain_id' => $alias->id,
        'ssl_id' => null,
    ]);

    $vhost = app(GenerateComponentNginxVhost::class)->generate($component->fresh());

    expect($vhost)->not->toContain('server_name mdbin.app www.mdbin.app;')
        ->and($vhost)->toContain('server_name mdbin.app;');
});

it('writes and symlinks the nginx vhost', function (): void {
    SSH::fake();
    $component = makeComponent($this);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'ssl_id' => null,
    ]);

    app(SyncComponentVhost::class)->sync($component->fresh());

    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
    SSH::assertExecutedContains('/etc/nginx/sites-enabled/component-'.$component->id);
    SSH::assertExecutedContains('service nginx reload');
});

it('writes and symlinks the caddy vhost', function (): void {
    SSH::fake();
    $component = makeComponent($this);
    $this->server->webserver()?->update(['name' => Caddy::id()]);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'ssl_id' => null,
    ]);

    app(SyncComponentVhost::class)->sync($component->fresh());

    SSH::assertExecutedContains('/etc/caddy/sites-available/component-'.$component->id);
    SSH::assertExecutedContains('/etc/caddy/sites-enabled/component-'.$component->id);
    SSH::assertExecutedContains('service caddy reload');
});

it('removes the vhost for a component with no http function', function (): void {
    SSH::fake();
    $component = makeComponent($this);
    $component->functions()->delete();

    app(SyncComponentVhost::class)->sync($component->fresh());

    SSH::assertExecutedContains('rm -f');
    SSH::assertNotExecutedContains('sites-enabled/component-'.$component->id.' ');
});

/**
 * The regression that fails if the vhost write is dispatched as a nested job instead of
 * called inline: the inner job would fail the same server lock and release(30) into a no-op.
 */
it('writes the vhost when called from inside a held server lock', function (): void {
    SSH::fake();
    $component = makeComponent($this);
    EnvironmentDomain::factory()->create([
        'environment_id' => $component->environment_id,
        'ssl_id' => null,
    ]);

    $caller = new class
    {
        use UniqueQueue;

        public function callWithinLock(Component $component): void
        {
            $this->run("server-{$component->server_id}", function () use ($component): void {
                app(SyncComponentVhost::class)->sync($component);
            });
        }
    };

    $caller->callWithinLock($component->fresh());

    SSH::assertExecutedContains('/etc/nginx/sites-available/component-'.$component->id);
});
