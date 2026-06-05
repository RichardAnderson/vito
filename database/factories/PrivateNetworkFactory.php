<?php

namespace Database\Factories;

use App\Enums\PrivateNetworkStatus;
use App\Models\PrivateNetwork;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateNetwork>
 */
class PrivateNetworkFactory extends Factory
{
    protected $model = PrivateNetwork::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => $this->faker->unique()->word,
            'subnet' => '10.'.$this->faker->unique()->numberBetween(1, 254).'.0.0/24',
            'mtu' => 1420,
            'status' => PrivateNetworkStatus::READY,
        ];
    }
}
