<?php

namespace App\Actions\Component;

use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\ComponentBackend;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use App\Models\EnvironmentDomain;
use App\Models\Ssl;
use Illuminate\Support\Collection;
use Mustache\Engine;

/**
 * The Caddy counterpart of GenerateComponentNginxVhost. The two builders are kept separate
 * because the socket form and the redirect-block shape genuinely diverge between webservers.
 */
class GenerateComponentCaddyVhost
{
    public function generate(Component $component): string
    {
        $engine = new Engine;
        $template = (string) file_get_contents(
            resource_path('views/ssh/services/webserver/caddy/vhost.mustache')
        );

        return format_webserver_config($engine->render($template, $this->buildData($component)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Component $component): array
    {
        $isLoadBalancer = $component->type === ComponentType::LOAD_BALANCER;
        $isReverseProxy = $component->type === ComponentType::PROXY;
        $isPhp = $component->type === ComponentType::APP && $component->php_version !== null;

        $data = [
            'primary_domain' => 'component-'.$component->id,
            'root' => rtrim($component->currentPath(), '/').'/'.ltrim((string) $component->web_directory, '/'),
            'is_php' => $isPhp,
            'is_reverse_proxy' => $isReverseProxy,
            'is_load_balancer' => $isLoadBalancer,
            'is_octane' => false,
            'octane_port' => 8000,
            'php_socket' => $isPhp ? $this->phpSocket($component) : '',
            'php_value' => false,
            'port' => (int) ($component->type_data['port'] ?? 80),
            'redirects' => [],
            'basic_auth_enabled' => false,
            'basic_auth_users' => [],
            'verification_key' => false,
            'has_http_verification' => false,
            'http_verification_domains' => [],
            'request_body_max_size' => false,
            ...$this->loadBalancerData($component, $isLoadBalancer),
        ];

        $data['server_blocks'] = $this->serverBlocks($component, $data);
        $data['redirect_blocks'] = $this->redirectBlocks($component);

        return $data;
    }

    /**
     * Caddy's socket form is `unix//run/...` — two slashes and no colon, unlike nginx.
     */
    private function phpSocket(Component $component): string
    {
        return "unix//run/php/php{$component->php_version}-fpm-{$component->user}.sock";
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBalancerData(Component $component, bool $isLoadBalancer): array
    {
        $data = [
            'lb_servers' => [],
            'lb_policy' => 'round_robin',
        ];

        if (! $isLoadBalancer) {
            return $data;
        }

        $data['lb_servers'] = $component->backends
            ->map(fn ($backend): array => [
                'address' => $this->backendAddress($backend).':'.$backend->port,
            ])
            ->all();

        return $data;
    }

    /**
     * @param  array<int, array<string, string>>  $domains
     * @return array<int, array<string, string>>
     */
    private function transformDomains(array $domains, bool $httpOnly): array
    {
        $prefix = $httpOnly ? 'http://' : '';

        return array_map(fn (array $domain): array => [
            'name' => $domain['name'],
            'address' => $prefix.$domain['name'],
        ], $domains);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function serverBlocks(Component $component, array $data): array
    {
        $blocks = [];

        foreach ($this->servedDomains($component)->groupBy(fn (EnvironmentDomain $domain): string => (string) ($domain->ssl_id ?? 'http')) as $sslId => $domains) {
            $hasSsl = $sslId !== 'http';
            $ssl = $hasSsl ? $domains->first()->ssl : null;
            $httpOnly = ! $hasSsl && $domains->first()->ssl_method === 'none';

            $blocks[] = [
                ...$data,
                'has_tls' => $hasSsl,
                'http_only' => $httpOnly,
                'ssl_certificate_path' => $this->certificatePath($ssl),
                'ssl_certificate_key_path' => $this->certificateKeyPath($ssl),
                'domains' => $this->transformDomains(
                    $domains->map(fn (EnvironmentDomain $domain): array => ['name' => $domain->host])->values()->all(),
                    $httpOnly
                ),
            ];
        }

        return $blocks;
    }

    /**
     * A redirect domain renders its own site block on the component serving the environment's
     * aliases, forwarding to the alias it points at.
     *
     * @return array<int, array<string, mixed>>
     */
    private function redirectBlocks(Component $component): array
    {
        if (! $this->isServingComponent($component)) {
            return [];
        }

        $blocks = [];

        $redirects = $component->environment->domains
            ->where('type', EnvironmentDomainType::REDIRECT)
            ->where('status', '!=', EnvironmentDomainStatus::FAILED);

        foreach ($redirects as $redirect) {
            $target = $redirect->redirectTo;

            if ($target === null) {
                continue;
            }

            $hasSsl = $redirect->ssl_id !== null && $redirect->ssl !== null;
            $httpOnly = ! $hasSsl && $redirect->ssl_method === 'none';

            $blocks[] = [
                'http_only' => $httpOnly,
                'has_tls' => $hasSsl,
                'ssl_certificate_path' => $hasSsl ? $redirect->ssl->certificate_path : '',
                'ssl_certificate_key_path' => $hasSsl ? $redirect->ssl->pk_path : '',
                'domains' => $this->transformDomains([['name' => $redirect->host]], $httpOnly),
                'redirect_target' => $this->redirectTarget($target),
                'redirect_mode' => 'permanent',
                'verification_key' => false,
            ];
        }

        return $blocks;
    }

    /**
     * A redirect always lands on an alias in the same environment, so the scheme it sends
     * visitors to is the one that alias itself answers on.
     */
    private function redirectTarget(EnvironmentDomain $alias): string
    {
        return ($alias->ssl_id !== null ? 'https' : 'http').'://'.$alias->host;
    }

    private function certificatePath(?Ssl $ssl): string
    {
        return $ssl !== null ? (string) $ssl->certificate_path : '';
    }

    private function certificateKeyPath(?Ssl $ssl): string
    {
        return $ssl !== null ? (string) $ssl->pk_path : '';
    }

    private function backendAddress(ComponentBackend $backend): string
    {
        $ip = $backend->networkServer?->ip;

        if ($ip === null || $ip === '') {
            $ip = (string) $backend->targetComponent->server->local_ip;
        }

        return $ip;
    }

    /**
     * Every domain in an environment is served by its one serving component, so any other
     * component renders a vhost with no site address of its own.
     *
     * @return Collection<int, EnvironmentDomain>
     */
    private function servedDomains(Component $component): Collection
    {
        if (! $this->isServingComponent($component)) {
            return collect();
        }

        return $component->environment->domains
            ->where('type', EnvironmentDomainType::ALIAS)
            ->where('status', '!=', EnvironmentDomainStatus::FAILED)
            ->values();
    }

    private function isServingComponent(Component $component): bool
    {
        return $component->environment->servingComponent()?->id === $component->id;
    }
}
