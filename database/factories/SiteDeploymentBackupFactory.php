<?php

namespace Database\Factories;

use App\Models\SiteDeploymentBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDeploymentBackup>
 */
class SiteDeploymentBackupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enabled' => true,
            'folders' => [],
            'databases' => [],
            'keep' => 5,
        ];
    }
}
