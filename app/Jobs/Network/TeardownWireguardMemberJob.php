<?php

namespace App\Jobs\Network;

use App\Actions\FirewallRule\ManageRule;
use App\Jobs\Network\Concerns\ManagesWireguardMesh;
use App\Models\PrivateNetworkMember;
use App\Models\Server;
use App\Models\ServerLog;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class TeardownWireguardMemberJob implements ShouldQueue
{
    use ManagesWireguardMesh;
    use Queueable;
    use UniqueQueue;

    public function __construct(
        protected int $serverId,
        protected string $iface,
        protected int $listenPort,
        protected ?int $memberId = null,
    ) {}

    public function handle(): void
    {
        $this->run("server-{$this->serverId}", function (): void {
            $server = Server::find($this->serverId);

            if ($server) {
                $this->bestEffortTeardown($server);
                $this->removeFirewallRules($server);
            }

            $member = $this->memberId ? PrivateNetworkMember::find($this->memberId) : null;

            if ($member) {
                $network = $member->privateNetwork;
                $member->delete();

                if ($network) {
                    $this->settleNetwork($network);
                }
            }
        });
    }

    public function failed(Exception $e): void
    {
        $server = Server::find($this->serverId);

        if ($server) {
            ServerLog::log($server, 'wireguard-teardown-failed', $e->getMessage());
        }
    }

    private function bestEffortTeardown(Server $server): void
    {
        try {
            $server->ssh()->exec(
                view('ssh.services.wireguard.teardown', ['iface' => $this->iface]),
                'wireguard-teardown'
            );
        } catch (Throwable $e) {
            ServerLog::log($server, 'wireguard-teardown-failed', $e->getMessage());
        }
    }

    private function removeFirewallRules(Server $server): void
    {
        if (! $this->memberId || ! $server->firewall()) {
            return;
        }

        $manageRule = app(ManageRule::class);

        $server->firewallRules()
            ->where('private_network_member_id', $this->memberId)
            ->get()
            ->each(fn ($rule) => $manageRule->delete($rule));
    }
}
