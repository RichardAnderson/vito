<?php

namespace App\Models;

use App\Actions\Site\DeleteDeploymentBackup;
use App\Enums\DeploymentStatus;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $site_id
 * @property int $deployment_script_id
 * @property int $log_id
 * @property string $commit_id
 * @property string $commit_id_short
 * @property array<string, mixed> $commit_data
 * @property DeploymentStatus $status
 * @property ?string $release
 * @property bool $active
 * @property bool $has_backups
 * @property ?array<string, mixed> $backup_manifest
 * @property Site $site
 * @property DeploymentScript $deploymentScript
 * @property ?ServerLog $log
 */
class Deployment extends AbstractModel
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'deployment_script_id',
        'log_id',
        'commit_id',
        'commit_data',
        'status',
        'release',
        'active',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'deployment_script_id' => 'integer',
        'log_id' => 'integer',
        'commit_data' => 'json',
        'active' => 'boolean',
        'has_backups' => 'boolean',
        'backup_manifest' => 'json',
        'status' => DeploymentStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Deployment $deployment): void {
            if ($deployment->has_backups) {
                app(DeleteDeploymentBackup::class)->delete($deployment);
            }
        });

        static::created(function (Deployment $deployment): void {
            $site = $deployment->site;
            $keep = $site->type_data['modern_deployment_history'] ?? 10;
            if ($site->deployments()->whereNotNull('release')->count() > $keep) {
                /** @var ?Deployment $lastDeploymentToKeep */
                $lastDeploymentToKeep = $site->deployments()->whereNotNull('release')->orderByDesc('id')->skip($keep)->first();
                if ($lastDeploymentToKeep) {
                    $deployments = $site->deployments()->whereNotNull('release')
                        ->where('id', '<=', $lastDeploymentToKeep->id)
                        ->get();
                    /** @var Deployment $deployment */
                    foreach ($deployments as $deployment) {
                        $deployment->remove(true);
                    }
                }
            }
        });
    }

    /**
     * @return BelongsTo<Site, covariant $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
        return $this->site->basePath().($this->release ? '/releases/'.$this->release : '');
    }

    public function remove(bool $onlyRelease = false): void
    {
        if ($this->release) {
            $site = $this->site;
            $site->server->ssh($site->user)->exec('rm -rf '.$this->path());
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
        $this->site->deployments()->update(['active' => false]);
        $this->active = true;
        $this->save();
    }

    /**
     * Sanitized summary of what the pre-deployment backup captured.
     * Never exposes absolute server paths.
     *
     * @return ?array{folders: array<int, string>, databases: array<int, string>}
     */
    public function backupFilesSummary(): ?array
    {
        if (! $this->has_backups || ! $this->backup_manifest) {
            return null;
        }

        $folders = array_values(array_map(
            fn (array $folder): string => basename((string) ($folder['abs_path'] ?? '')),
            $this->backup_manifest['folders'] ?? []
        ));

        $databases = array_values(array_map(
            fn (array $database): string => (string) ($database['name'] ?? ''),
            $this->backup_manifest['databases'] ?? []
        ));

        return [
            'folders' => $folders,
            'databases' => $databases,
        ];
    }
}
