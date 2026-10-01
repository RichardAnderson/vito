<?php

namespace Database\Factories;

use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use App\Models\Environment;
use App\Models\EnvironmentDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentDomain>
 */
class EnvironmentDomainFactory extends Factory
{
    protected $model = EnvironmentDomain::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'host' => $this->faker->unique()->domainName(),
            'type' => EnvironmentDomainType::ALIAS,
            'ssl_method' => 'none',
            'status' => EnvironmentDomainStatus::ACTIVE,
        ];
    }
}
