<?php

namespace App\Models;

use App\Enums\DeploymentStage;
use App\Enums\EnvironmentDeploymentStatus;
use Database\Factories\EnvironmentDeploymentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $environment_id
 * @property ?string $commit_id
 * @property ?array<string, mixed> $commit_data
 * @property EnvironmentDeploymentStatus $status
 * @property ?DeploymentStage $current_stage
 * @property ?string $release
 * @property Environment $environment
 * @property Collection<int, Deployment> $deployments
 */
class EnvironmentDeployment extends AbstractModel
{
    /** @use HasFactory<EnvironmentDeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'environment_id',
        'commit_id',
        'commit_data',
        'status',
        'current_stage',
        'release',
    ];

    protected $casts = [
        'environment_id' => 'integer',
        'commit_data' => 'array',
        'status' => EnvironmentDeploymentStatus::class,
        'current_stage' => DeploymentStage::class,
    ];

    /**
     * @return BelongsTo<Environment, covariant $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return HasMany<Deployment, covariant $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }
}
