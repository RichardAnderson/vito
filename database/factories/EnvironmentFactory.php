<?php

namespace Database\Factories;

use App\Enums\EnvironmentStatus;
use App\Models\Application;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Environment>
 */
class EnvironmentFactory extends Factory
{
    protected $model = Environment::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'application_id' => Application::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'branch' => 'main',
            'color' => 'slate',
            'auto_deploy' => false,
            'status' => EnvironmentStatus::READY,
        ];
    }
}
