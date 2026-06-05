<?php

namespace App\Jobs\Network;

use App\Actions\FirewallRule\ManageRule;
use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Enums\ServiceStatus;
use App\Jobs\Network\Concerns\ManagesWireguardMesh;
use App\Models\PrivateNetworkMember;
use App\Models\Server;
use App\Models\ServerLog;
use App\Models\Service;
use App\Services\Vpn\WireGuard;
use App\Support\Cidr;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ConfigureWireguardMemberJob implements ShouldQueue
{
    use ManagesWireguardMesh;
    use Queueable;
    use UniqueQueue;

    public function __construct(protected PrivateNetworkMember $member) {}

    public function handle(): void
    {
        $this->run("server-{$this->member->server_id}", function (): void {
            $member = $this->member->fresh(['server', 'privateNetwork']);

            if (! $member || $member->status === MemberStatus::LEAVING) {
                return;
            }

            $server = $member->server;
            $network = $member->privateNetwork;

            if (! $server || ! $network) {
                return;
            }

            if (! $server->isReady()) {
                $this->release(30);

                return;
            }

            $this->ensureWireguardInstalled($server);
            $this->ensureFirewallRules($member, $server);

            $publicKey = trim($server->ssh()->exec(
                view('ssh.services.wireguard.genkey', ['iface' => $member->interface]),
                'wireguard-genkey'
            ));

            $member->public_key = $publicKey;
            $member->endpoint = $server->ip.':'.$member->listen_port;
            $member->status = MemberStatus::ACTIVE;
            $member->error = null;
            $member->save();

            $this->renderMemberConfig($member);

            $server->systemd()->enable("wg-quick@{$member->interface}");

            $this->broadcastMember($member);

            foreach ($network->members()->where('server_id', '!=', $member->server_id)->get() as $peer) {
                dispatch(new SyncWireguardPeersJob($peer))->onQueue('ssh');
            }

            $this->settleNetwork($network);
        });
    }

    public function failed(Exception $e): void
    {
        $member = $this->member->fresh('privateNetwork');

        if (! $member) {
            return;
        }

        $member->status = MemberStatus::FAILED;
        $member->error = $e->getMessage();
        $member->save();

        $this->broadcastMember($member);

        if ($member->privateNetwork) {
            $this->settleNetwork($member->privateNetwork);
        }

        ServerLog::log($this->member->server, 'wireguard-configure-failed', $e->getMessage());
    }

    private function ensureWireguardInstalled(Server $server): void
    {
        if ($server->service('vpn')) {
            return;
        }

        $service = new Service([
            'server_id' => $server->id,
            'name' => WireGuard::id(),
            'type' => WireGuard::type(),
            'version' => 'latest',
            'status' => ServiceStatus::INSTALLING,
        ]);
        $service->is_default = true;
        $service->save();
        $service->newLog();

        $service->handler()->install();

        $service->status = ServiceStatus::READY;
        $service->installed_version = $service->handler()->version();
        $service->save();
    }

    private function ensureFirewallRules(PrivateNetworkMember $member, Server $server): void
    {
        if (! $server->firewall()) {
            return;
        }

        if ($member->firewallRules()->exists()) {
            return;
        }

        app(ManageRule::class)->createMany($server, [
            [
                'name' => "WG({$member->interface}) HS",
                'type' => 'allow',
                'protocol' => 'udp',
                'port' => (string) $member->listen_port,
                'source_any' => true,
            ],
            [
                'name' => "WG({$member->interface}) NET",
                'type' => 'allow',
                'protocol' => 'any',
                'source' => Cidr::networkAddress($member->privateNetwork->subnet),
                'mask' => $member->privateNetwork->prefix(),
            ],
        ], $member->id);
    }
}
