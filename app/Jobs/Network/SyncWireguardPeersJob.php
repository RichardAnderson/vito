<?php

namespace App\Jobs\Network;

use App\Enums\MemberStatus;
use App\Jobs\Network\Concerns\ManagesWireguardMesh;
use App\Models\PrivateNetworkMember;
use App\Models\ServerLog;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SyncWireguardPeersJob implements ShouldQueue
{
    use ManagesWireguardMesh;
    use Queueable;
    use UniqueQueue;

    public function __construct(protected PrivateNetworkMember $member) {}

    public function handle(): void
    {
        $this->run("server-{$this->member->server_id}", function (): void {
            $member = $this->member->fresh(['server', 'privateNetwork']);

            if (! $member) {
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

            if ($member->status !== MemberStatus::ACTIVE || ! $member->public_key) {
                return;
            }

            $this->renderMemberConfig($member);

            $status = $server->systemd()->status("wg-quick@{$member->interface}");

            if (! Str::contains($status, 'Active: active')) {
                return;
            }

            $server->ssh()->exec(
                view('ssh.services.wireguard.syncconf', ['iface' => $member->interface]),
                'wireguard-syncconf'
            );

            $this->settleNetwork($network);
        });
    }

    public function failed(Exception $e): void
    {
        ServerLog::log($this->member->server, 'wireguard-sync-failed', $e->getMessage());
    }
}
