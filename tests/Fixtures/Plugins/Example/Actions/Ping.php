<?php

namespace Tests\Fixtures\Plugins\Example\Actions;

use Vito\Plugin\Contracts\Server;
use Vito\Plugin\Contracts\Site;
use Vito\Plugin\Facades\Broadcast;
use Vito\Plugin\Facades\Ssh;

/**
 * Demonstrates the consumable Host API surface a plugin gets TODAY:
 *  - typed against the SDK contracts (Server/Site), never App\Models\*;
 *  - reads stable accessors through those contracts;
 *  - runs an SSH command via the capability facade (host-provided helper);
 *  - broadcasts a socket event via the capability facade.
 */
final class Ping
{
    public function handle(Server $server, Site $site): string
    {
        $output = trim(Ssh::init($server)->exec('echo pong'));

        Broadcast::dispatch($server->project_id, 'example.pinged', [
            'summary' => "{$site->domain} on {$server->name}",
            'output' => $output,
        ]);

        return $output;
    }
}
