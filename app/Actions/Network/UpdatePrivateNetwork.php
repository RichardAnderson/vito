<?php

namespace App\Actions\Network;

use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Jobs\Network\RestartWireguardMemberJob;
use App\Models\PrivateNetwork;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdatePrivateNetwork
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(PrivateNetwork $network, array $input): PrivateNetwork
    {
        $this->validate($network, $input);

        $mtuChanged = array_key_exists('mtu', $input) && (int) $input['mtu'] !== $network->mtu;

        $network->name = $input['name'];
        $network->mtu = $input['mtu'] ?? $network->mtu;
        $network->save();

        if ($mtuChanged) {
            $this->restartMembers($network);
        }

        return $network;
    }

    private function restartMembers(PrivateNetwork $network): void
    {
        $members = $network->members()->where('status', MemberStatus::ACTIVE)->get();

        if ($members->isEmpty()) {
            return;
        }

        $network->status = PrivateNetworkStatus::UPDATING;
        $network->save();

        foreach ($members as $member) {
            dispatch(new RestartWireguardMemberJob($member))->onQueue('ssh');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(PrivateNetwork $network, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('private_networks', 'name')
                    ->where('project_id', $network->project_id)
                    ->ignore($network->id),
            ],
            'mtu' => [
                'nullable',
                'integer',
                'min:1280',
                'max:1500',
            ],
        ])->validate();
    }
}
