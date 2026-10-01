<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property ?int $source_control_id
 * @property string $name
 * @property ?string $repository
 * @property ApplicationStatus $status
 * @property Project $project
 * @property ?SourceControl $sourceControl
 * @property Collection<int, Environment> $environments
 */
class Application extends AbstractModel
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'source_control_id',
        'name',
        'repository',
        'status',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'source_control_id' => 'integer',
        'status' => ApplicationStatus::class,
    ];

    /**
     * @return BelongsTo<Project, covariant $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<SourceControl, covariant $this>
     */
    public function sourceControl(): BelongsTo
    {
        return $this->belongsTo(SourceControl::class)->withTrashed();
    }

    /**
     * @return HasMany<Environment, covariant $this>
     */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }
}
