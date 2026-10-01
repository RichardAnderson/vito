<?php

namespace Database\Factories;

use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\Environment;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    protected $model = Component::class;

    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'server_id' => Server::factory(),
            'name' => $this->faker->unique()->word(),
            'type' => ComponentType::APP,
            'status' => ComponentStatus::READY,
            'web_directory' => '/public',
            'php_version' => '8.4',
        ];
    }
}
