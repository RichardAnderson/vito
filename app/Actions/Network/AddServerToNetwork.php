<?php

namespace App\Actions\Network;

use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use App\Jobs\Network\ConfigureWireguardMemberJob;
use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkMember;
use App\Models\Server;
use App\Support\Cidr;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddServerToNetwork
{
    private const PORT_BASE = 51820;

    private const PORT_MAX = 52820;

    private const MAX_INTERFACES = 100;

    private const ALLOC_ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $input
     */
    public function add(PrivateNetwork $network, array $input): PrivateNetworkMember
    {
        $this->validate($network, $input);

        /** @var Server $server */
        $server = Server::query()->findOrFail($input['server_id']);

        $member = $this->allocate($network, $server);

        $network->status = PrivateNetworkStatus::UPDATING;
        $network->save();

        dispatch(new ConfigureWireguardMemberJob($member))->onQueue('ssh');

        return $member;
    }

    private function allocate(PrivateNetwork $network, Server $server): PrivateNetworkMember
    {
        $lock = Cache::lock("alloc-server-{$server->id}", 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'server_id' => __('The server is busy joining another network; please retry.'),
            ]);
        }

        try {
            for ($attempt = 1; $attempt <= self::ALLOC_ATTEMPTS; $attempt++) {
                try {
                    return DB::transaction(fn (): PrivateNetworkMember => PrivateNetworkMember::create([
                        'private_network_id' => $network->id,
                        'server_id' => $server->id,
                        'overlay_ip' => $this->nextOverlayIp($network),
                        'interface' => $this->nextInterface($server),
                        'listen_port' => $this->nextPort($server),
                        'status' => MemberStatus::JOINING,
                    ]));
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e) || $attempt === self::ALLOC_ATTEMPTS) {
                        throw $e;
                    }
                }
            }
        } finally {
            $lock->release();
        }

        throw ValidationException::withMessages([
            'server_id' => __('Could not allocate an overlay address; please retry.'),
        ]);
    }

    private function nextOverlayIp(PrivateNetwork $network): string
    {
        $taken = $network->members()->pluck('overlay_ip')->all();
        $ip = Cidr::nextFreeIp($network->subnet, $taken);

        if (! $ip) {
            throw ValidationException::withMessages([
                'server_id' => __('The private network has no free overlay addresses left.'),
            ]);
        }

        return $ip;
    }

    private function nextInterface(Server $server): string
    {
        $used = $server->privateNetworkMembers()->pluck('interface')->all();

        for ($i = 0; $i < self::MAX_INTERFACES; $i++) {
            if (! in_array("wg{$i}", $used, true)) {
                return "wg{$i}";
            }
        }

        throw ValidationException::withMessages([
            'server_id' => __('The server has no free WireGuard interface slots left.'),
        ]);
    }

    private function nextPort(Server $server): int
    {
        $used = $server->privateNetworkMembers()->pluck('listen_port')->all();

        for ($port = self::PORT_BASE; $port <= self::PORT_MAX; $port++) {
            if (! in_array($port, $used, true)) {
                return $port;
            }
        }

        throw ValidationException::withMessages([
            'server_id' => __('The server has no free WireGuard ports left.'),
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(PrivateNetwork $network, array $input): void
    {
        if ($network->status === PrivateNetworkStatus::UPDATING) {
            throw ValidationException::withMessages([
                'server_id' => __('The private network is currently updating; please wait and retry.'),
            ]);
        }

        Validator::make($input, [
            'server_id' => [
                'required',
                Rule::in($network->project->servers()->pluck('id')->all()),
                Rule::unique('private_network_members', 'server_id')
                    ->where('private_network_id', $network->id),
            ],
        ], [
            'server_id.in' => __('The selected server does not belong to this project.'),
            'server_id.unique' => __('The server is already a member of this private network.'),
        ])->validate();
    }
}
