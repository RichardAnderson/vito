<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EnvironmentVariable>
 */
class EnvironmentVariableFactory extends Factory
{
    protected $model = EnvironmentVariable::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'key' => Str::upper($this->faker->unique()->word()),
            'value' => $this->faker->word(),
            'is_secret' => false,
        ];
    }

    public function secret(): static
    {
        return $this->state(fn (): array => ['is_secret' => true]);
    }
}
