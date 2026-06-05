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

class RestartWireguardMemberJob implements ShouldQueue
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

            $server->systemd()->restart("wg-quick@{$member->interface}");

            $this->broadcastMember($member);
            $this->settleNetwork($network);
        });
    }

    public function failed(Exception $e): void
    {
        ServerLog::log($this->member->server, 'wireguard-restart-failed', $e->getMessage());
    }
}
