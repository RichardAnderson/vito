<?php

namespace App\Actions\Component;

use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\NetworkServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateComponentBackends
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(Component $loadBalancer, array $input): void
    {
        if ($loadBalancer->type !== ComponentType::LOAD_BALANCER) {
            throw ValidationException::withMessages([
                'component_id' => 'Only a load balancer component has backends.',
            ]);
        }

        Validator::make($input, [
            'backends' => ['required', 'array', 'min:1'],
            'backends.*.target_component_id' => [
                'required',
                Rule::exists('components', 'id')->where('environment_id', $loadBalancer->environment_id),
            ],
            'backends.*.port' => ['required', 'integer', 'min:1', 'max:65535'],
        ])->validate();

        $resolved = [];

        foreach ($input['backends'] as $backend) {
            /** @var Component $target */
            $target = Component::query()->findOrFail($backend['target_component_id']);

            if ($target->id === $loadBalancer->id) {
                throw ValidationException::withMessages([
                    'backends' => 'A load balancer cannot point at itself.',
                ]);
            }

            if (! $target->hasFunction(ComponentFunctions::HTTP)) {
                throw ValidationException::withMessages([
                    'backends' => 'Component "'.$target->name.'" does not serve HTTP.',
                ]);
            }

            $resolved[] = [
                'target_component_id' => $target->id,
                'network_server_id' => $this->sharedNetworkServerId($loadBalancer, $target),
                'port' => (int) $backend['port'],
            ];
        }

        DB::transaction(function () use ($loadBalancer, $resolved): void {
            $loadBalancer->backends()->delete();

            foreach ($resolved as $backend) {
                $loadBalancer->backends()->create($backend);
            }
        });

        $loadBalancer->unsetRelation('backends');
    }

    /**
     * Backends are addressed by their private network IP, never by `servers.local_ip`.
     *
     * @throws ValidationException
     */
    private function sharedNetworkServerId(Component $loadBalancer, Component $target): int
    {
        if ($loadBalancer->server_id === $target->server_id) {
            $sameServer = NetworkServer::query()
                ->where('server_id', $target->server_id)
                ->whereNotNull('ip')
                ->value('id');

            if ($sameServer !== null) {
                return (int) $sameServer;
            }
        }

        $loadBalancerNetworks = NetworkServer::query()
            ->where('server_id', $loadBalancer->server_id)
            ->pluck('network_id');

        $networkServerId = NetworkServer::query()
            ->where('server_id', $target->server_id)
            ->whereIn('network_id', $loadBalancerNetworks)
            ->whereNotNull('ip')
            ->value('id');

        if ($networkServerId === null) {
            throw ValidationException::withMessages([
                'backends' => 'Servers "'.$loadBalancer->server->name.'" and "'.$target->server->name.'" share no private network with an allocated address.',
            ]);
        }

        return (int) $networkServerId;
    }
}
