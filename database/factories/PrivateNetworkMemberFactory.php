<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\PrivateNetwork;
use App\Models\PrivateNetworkMember;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateNetworkMember>
 */
class PrivateNetworkMemberFactory extends Factory
{
    protected $model = PrivateNetworkMember::class;

    public function definition(): array
    {
        return [
            'private_network_id' => PrivateNetwork::factory(),
            'server_id' => Server::factory(),
            'overlay_ip' => '10.88.0.'.$this->faker->unique()->numberBetween(2, 254),
            'interface' => 'wg0',
            'listen_port' => $this->faker->unique()->numberBetween(51820, 52820),
            'public_key' => $this->faker->sha256,
            'endpoint' => $this->faker->ipv4().':51820',
            'status' => MemberStatus::ACTIVE,
        ];
    }
}
