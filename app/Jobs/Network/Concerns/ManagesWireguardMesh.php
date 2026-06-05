<?php

namespace App\Jobs\Network\Concerns;

use App\DTOs\SocketEventDTO;
use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Events\SocketEvent;
use App\Exceptions\SSHError;
use App\Http\Resources\PrivateNetworkMemberResource;
use App\Http\Resources\PrivateNetworkResource;
use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkMember;

trait ManagesWireguardMesh
{
    /**
     * @throws SSHError
     */
    protected function renderMemberConfig(PrivateNetworkMember $member): void
    {
        $network = $member->privateNetwork;
        $server = $member->server;

        $server->ssh()->exec(
            view('ssh.services.wireguard.render-conf', [
                'iface' => $member->interface,
                'overlayIp' => $member->overlay_ip,
                'prefix' => $network->prefix(),
                'listenPort' => $member->listen_port,
                'mtu' => $network->mtu,
                'peers' => $network->renderablePeers($member->server_id),
            ]),
            'wireguard-render-conf'
        );
    }

    protected function settleNetwork(PrivateNetwork $network): void
    {
        if ($network->status !== PrivateNetworkStatus::UPDATING) {
            return;
        }

        $pending = $network->members()
            ->whereIn('status', [MemberStatus::JOINING, MemberStatus::LEAVING])
            ->exists();

        if ($pending) {
            return;
        }

        $network->status = PrivateNetworkStatus::READY;
        $network->save();

        $this->broadcastNetwork($network);
    }

    protected function broadcastMember(PrivateNetworkMember $member): void
    {
        $member->loadMissing('server');

        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $member->server->project_id,
            type: 'private-network-member.updated',
            data: new PrivateNetworkMemberResource($member),
        ));
    }

    protected function broadcastNetwork(PrivateNetwork $network): void
    {
        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $network->project_id,
            type: 'private-network.updated',
            data: new PrivateNetworkResource($network),
        ));
    }
}
