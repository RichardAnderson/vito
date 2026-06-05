<?php

namespace App\Actions\Network;

use App\Actions\FirewallRule\ManageRule;
use App\Jobs\Network\TeardownWireguardMemberJob;
use App\Models\PrivateNetwork;

class DeletePrivateNetwork
{
    public function delete(PrivateNetwork $network): void
    {
        $members = $network->members()->with(['server', 'firewallRules'])->get();
        $manageRule = app(ManageRule::class);

        foreach ($members as $member) {
            if ($member->server?->firewall()) {
                $member->firewallRules->each(fn ($rule) => $manageRule->delete($rule));
            }

            dispatch(new TeardownWireguardMemberJob(
                $member->server_id,
                $member->interface,
                $member->listen_port,
            ))->onQueue('ssh');
        }

        $network->delete();
    }
}
