<?php

namespace Database\Factories;

use App\Enums\EnvironmentDeploymentStatus;
use App\Models\Environment;
use App\Models\EnvironmentDeployment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentDeployment>
 */
class EnvironmentDeploymentFactory extends Factory
{
    protected $model = EnvironmentDeployment::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'commit_id' => $this->faker->sha1(),
            'status' => EnvironmentDeploymentStatus::PENDING,
        ];
    }
}
