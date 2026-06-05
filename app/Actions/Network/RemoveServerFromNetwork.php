<?php

namespace App\Actions\Network;

use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Jobs\Network\SyncWireguardPeersJob;
use App\Jobs\Network\TeardownWireguardMemberJob;
use App\Models\PrivateNetwork;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveServerFromNetwork
{
    public function remove(PrivateNetwork $network, Server $server): void
    {
        $member = $network->members()->where('server_id', $server->id)->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'server_id' => __('The server is not a member of this private network.'),
            ]);
        }

        DB::transaction(function () use ($member, $network): void {
            $member->status = MemberStatus::LEAVING;
            $member->save();

            $network->status = PrivateNetworkStatus::UPDATING;
            $network->save();
        });

        foreach ($network->members()->where('status', MemberStatus::ACTIVE)->get() as $survivor) {
            dispatch(new SyncWireguardPeersJob($survivor))->onQueue('ssh');
        }

        dispatch(new TeardownWireguardMemberJob(
            $member->server_id,
            $member->interface,
            $member->listen_port,
            $member->id,
        ))->onQueue('ssh');
    }
}
