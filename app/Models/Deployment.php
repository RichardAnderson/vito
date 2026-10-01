<?php

namespace App\Models;

use App\Enums\DeploymentStage;
use App\Enums\DeploymentStatus;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $site_id
 * @property ?int $component_id
 * @property ?int $environment_deployment_id
 * @property ?int $deployment_script_id
 * @property int $log_id
 * @property string $commit_id
 * @property string $commit_id_short
 * @property array<string, mixed> $commit_data
 * @property DeploymentStatus $status
 * @property ?string $release
 * @property bool $active
 * @property ?DeploymentStage $current_stage
 * @property ?Site $site
 * @property ?Component $component
 * @property ?EnvironmentDeployment $environmentDeployment
 * @property ?DeploymentScript $deploymentScript
 * @property ?ServerLog $log
 */
class Deployment extends AbstractModel
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'component_id',
        'environment_deployment_id',
        'deployment_script_id',
        'log_id',
        'commit_id',
        'commit_data',
        'status',
        'current_stage',
        'release',
        'active',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'component_id' => 'integer',
        'environment_deployment_id' => 'integer',
        'deployment_script_id' => 'integer',
        'log_id' => 'integer',
        'commit_data' => 'json',
        'active' => 'boolean',
        'status' => DeploymentStatus::class,
        'current_stage' => DeploymentStage::class,
    ];

    protected static function booted(): void
    {
        static::created(function (Deployment $deployment): void {
            if ($deployment->release === null) {
                return;
            }

            $deployment->pruneOldReleases();
        });
    }

    /**
     * Component releases are stamped by the clone stage, which is an update and therefore
     * never reaches the created hook — that stage calls this method explicitly.
     */
    public function pruneOldReleases(): void
    {
        $owner = $this->site ?? $this->component;

        if ($owner === null) {
            return;
        }

        $keep = $owner instanceof Site
            ? ($owner->type_data['modern_deployment_history'] ?? 10)
            : 10;

        if ($owner->deployments()->whereNotNull('release')->count() <= $keep) {
            return;
        }

        /** @var ?Deployment $lastDeploymentToKeep */
        $lastDeploymentToKeep = $owner->deployments()->whereNotNull('release')->orderByDesc('id')->skip($keep)->first();

        if (! $lastDeploymentToKeep) {
            return;
        }

        $deployments = $owner->deployments()->whereNotNull('release')
            ->where('id', '<=', $lastDeploymentToKeep->id)
            ->get();

        /** @var Deployment $deployment */
        foreach ($deployments as $deployment) {
            $deployment->remove(true);
        }
    }

    /**
     * @return BelongsTo<Site, covariant $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * @return BelongsTo<EnvironmentDeployment, covariant $this>
     */
    public function environmentDeployment(): BelongsTo
    {
        return $this->belongsTo(EnvironmentDeployment::class);
    }

    /**
     * @return BelongsTo<DeploymentScript, covariant $this>
     */
    public function deploymentScript(): BelongsTo
    {
        return $this->belongsTo(DeploymentScript::class);
    }

    /**
     * @return BelongsTo<ServerLog, covariant $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ServerLog::class, 'log_id');
    }

    public function path(): string
    {
        $basePath = $this->site?->basePath() ?? $this->component?->basePath() ?? '';

        return $basePath.($this->release ? '/releases/'.$this->release : '');
    }

    public function remove(bool $onlyRelease = false): void
    {
        if ($this->release) {
            $owner = $this->site ?? $this->component;

            if ($owner !== null) {
                $owner->server->ssh($owner->user)->exec('rm -rf '.$this->path());
            }

            $this->release = null;
        }

        if ($onlyRelease) {
            $this->save();

            return;
        }

        $this->delete();
    }

    public function activate(): void
    {
        $owner = $this->component_id !== null ? $this->component : $this->site;
        $owner?->deployments()->update(['active' => false]);
        $this->active = true;
        $this->save();
    }
}
