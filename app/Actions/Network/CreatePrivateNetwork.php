<?php

namespace App\Actions\Network;

use App\Enums\IpAddressType;
use App\Enums\PrivateNetworkStatus;
use App\Models\PrivateNetwork;
use App\Models\Project;
use App\Models\ServerIpAddress;
use App\Support\Cidr;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePrivateNetwork
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Project $project, array $input): PrivateNetwork
    {
        $this->validate($project, $input);

        $network = new PrivateNetwork([
            'project_id' => $project->id,
            'name' => $input['name'],
            'subnet' => $input['subnet'],
            'mtu' => $input['mtu'] ?? 1420,
            'status' => PrivateNetworkStatus::READY,
        ]);
        $network->save();

        return $network;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(Project $project, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('private_networks', 'name')->where('project_id', $project->id),
            ],
            'subnet' => [
                'required',
                'string',
                fn (string $attribute, mixed $value, Closure $fail) => $this->validateSubnet($project, $value, $fail),
            ],
            'mtu' => [
                'nullable',
                'integer',
                'min:1280',
                'max:1500',
            ],
        ])->validate();
    }

    private function validateSubnet(Project $project, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cidr::isValidPrivateV4($value)) {
            $fail('The subnet must be a valid private IPv4 CIDR (e.g. 10.88.0.0/24).');

            return;
        }

        $conflict = $project->privateNetworks()
            ->pluck('subnet')
            ->contains(fn (string $subnet): bool => Cidr::overlaps($subnet, $value));

        if ($conflict) {
            $fail('The subnet overlaps another private network in this project.');

            return;
        }

        $clashingIp = $this->existingPrivateIps($project)
            ->first(fn (string $ip): bool => Cidr::containsIp($value, $ip));

        if ($clashingIp) {
            $fail("The subnet contains a private IP already assigned to a server ({$clashingIp}). Pick a non-overlapping range.");
        }
    }

    /**
     * Private IPv4 addresses already configured on this project's servers (provider private
     * networks etc.), which the overlay subnet must not collide with.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function existingPrivateIps(Project $project): Collection
    {
        $serverIds = $project->servers()->pluck('id');

        return ServerIpAddress::query()
            ->whereIn('server_id', $serverIds)
            ->where('type', IpAddressType::PRIVATE)
            ->pluck('ip')
            ->merge($project->servers()->whereNotNull('local_ip')->pluck('local_ip'))
            ->filter(fn (?string $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            ->unique()
            ->values();
    }
}
