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
 * Renders the shared nginx vhost template for a Component. The template and
 * `AbstractGenerateConfig` are deliberately untouched: `primary_domain` is consumed only by
 * the two access/error log lines, so passing `component-{id}` is all NF2 needs.
 */
class GenerateComponentNginxVhost
{
    /**
     * `$httpOnly` renders the plaintext vhost the nginx certificate flow needs in place before
     * `certbot --nginx` runs: that authenticator requires an existing server_name block.
     */
    public function generate(Component $component, bool $httpOnly = false): string
    {
        $engine = new Engine;
        $template = (string) file_get_contents(
            resource_path('views/ssh/services/webserver/nginx/vhost.mustache')
        );

        return format_webserver_config($engine->render($template, $this->buildData($component, $httpOnly)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Component $component, bool $httpOnly): array
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
            'basic_auth_realm' => '',
            'basic_auth_file' => '',
            'basic_auth_users' => [],
            'verification_key' => false,
            'has_octane_map' => false,
            'has_force_ssl_redirect' => false,
            'force_ssl_domains' => [],
            'client_max_body_size' => false,
            'fastcgi_read_timeout' => false,
            ...$this->upstreamData($component, $isLoadBalancer),
        ];

        $data['server_blocks'] = $this->serverBlocks($component, $data, $httpOnly);
        $data['redirect_blocks'] = $this->redirectBlocks($component);

        return $data;
    }

    private function phpSocket(Component $component): string
    {
        return "unix:/run/php/php{$component->php_version}-fpm-{$component->user}.sock";
    }

    /**
     * @return array<string, mixed>
     */
    private function upstreamData(Component $component, bool $isLoadBalancer): array
    {
        $data = [
            'has_upstream' => $isLoadBalancer,
            'upstream_name' => 'component_'.$component->id.'_backend',
            'upstream_method_least_conn' => false,
            'upstream_method_ip_hash' => false,
            'upstream_servers' => [],
        ];

        if (! $isLoadBalancer) {
            return $data;
        }

        $data['upstream_servers'] = $component->backends
            ->map(fn ($backend): array => [
                'ip' => $this->backendAddress($backend),
                'port' => $backend->port,
                'backup' => false,
                'has_weight' => false,
                'weight' => null,
            ])
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function serverBlocks(Component $component, array $data, bool $httpOnly): array
    {
        $blocks = [];

        foreach ($this->servedDomains($component)->groupBy(fn (EnvironmentDomain $domain): string => (string) ($domain->ssl_id ?? 'http')) as $sslId => $domains) {
            $hasSsl = ! $httpOnly && $sslId !== 'http';
            $ssl = $hasSsl ? $domains->first()->ssl : null;

            $blocks[] = [
                ...$data,
                'listen_80' => ! $hasSsl,
                'listen_443' => $hasSsl,
                'ssl_certificate_path' => $this->certificatePath($ssl),
                'ssl_certificate_key_path' => $this->certificateKeyPath($ssl),
                'domains' => $domains->map(fn (EnvironmentDomain $domain): array => ['name' => $domain->host])->values()->all(),
            ];
        }

        return $blocks;
    }

    /**
     * A redirect domain renders its own server block on the component serving the environment's
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

            $blocks[] = [
                'listen_80' => true,
                'listen_443' => $hasSsl,
                'ssl_certificate_path' => $hasSsl ? $redirect->ssl->certificate_path : '',
                'ssl_certificate_key_path' => $hasSsl ? $redirect->ssl->pk_path : '',
                'domains' => [['name' => $redirect->host]],
                'redirect_target' => $this->redirectTarget($target),
                'redirect_status' => 301,
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
     * component renders a vhost with no server_name of its own.
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
