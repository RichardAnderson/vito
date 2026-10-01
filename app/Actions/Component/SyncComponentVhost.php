<?php

namespace App\Actions\Component;

use App\Exceptions\SSHError;
use App\Models\Component;
use App\Services\Webserver\Caddy;
use App\Services\Webserver\Nginx;
use Illuminate\Validation\ValidationException;

/**
 * Writes, reloads or removes a Component's vhost.
 *
 * This is an Action rather than only a job because every caller that already holds the
 * `server-{id}` UniqueQueue lock must invoke it inline: a nested dispatch on the same key
 * fails `$lock->get()` and falls to `release(30)`, which is a no-op on the sync connection
 * the test suite forces. No job dispatches another job keyed on a server whose lock it holds.
 *
 * @throws SSHError
 */
class SyncComponentVhost
{
    public function sync(Component $component, bool $httpOnly = false): void
    {
        $component->loadMissing([
            'environment.domains.ssl',
            'environment.domains.redirectTo',
            'environment.components.functions',
            'backends.networkServer',
            'backends.targetComponent.server',
        ]);

        if (! $component->terminatesHttp()) {
            $this->remove($component);

            return;
        }

        match ($this->handlerId($component)) {
            Caddy::id() => $this->writeCaddy($component),
            default => $this->writeNginx($component, $httpOnly),
        };
    }

    /**
     * @throws SSHError
     */
    public function remove(Component $component): void
    {
        $server = $component->server;
        $view = $this->handlerId($component) === Caddy::id()
            ? 'ssh.services.webserver.caddy.remove-component-vhost'
            : 'ssh.services.webserver.nginx.remove-component-vhost';

        $server->ssh()->exec(
            view($view, ['name' => $this->fileName($component)]),
            'remove-component-vhost'
        );
    }

    public function fileName(Component $component): string
    {
        return 'component-'.$component->id;
    }

    /**
     * @throws SSHError
     */
    private function writeNginx(Component $component, bool $httpOnly): void
    {
        $vhost = app(GenerateComponentNginxVhost::class)->generate($component, $httpOnly);
        $name = $this->fileName($component);
        $server = $component->server;

        $server->ssh()->write('/etc/nginx/sites-available/'.$name, $vhost, 'root');
        $server->ssh()->exec(
            view('ssh.services.webserver.nginx.create-vhost', ['domain' => $name, 'vhost' => $vhost]),
            'create-component-vhost'
        );
    }

    /**
     * @throws SSHError
     */
    private function writeCaddy(Component $component): void
    {
        $vhost = app(GenerateComponentCaddyVhost::class)->generate($component);
        $name = $this->fileName($component);
        $server = $component->server;

        $server->ssh()->write('/etc/caddy/sites-available/'.$name, $vhost, 'root');
        $server->ssh()->exec(
            view('ssh.services.webserver.caddy.create-vhost', ['domain' => $name]),
            'create-component-vhost'
        );
    }

    /**
     * @throws ValidationException
     */
    private function handlerId(Component $component): string
    {
        $webserver = $component->server->webserver();

        if ($webserver === null) {
            throw ValidationException::withMessages([
                'server_id' => 'The server has no webserver installed.',
            ]);
        }

        return $webserver->name === Caddy::id() ? Caddy::id() : Nginx::id();
    }
}
