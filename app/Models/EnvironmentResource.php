<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $environment_id
 * @property string $resource_type
 * @property int $resource_id
 * @property ?string $alias
 * @property Environment $environment
 * @property ?Model $resource
 */
class EnvironmentResource extends AbstractModel
{
    protected $fillable = [
        'environment_id',
        'resource_type',
        'resource_id',
        'alias',
    ];

    protected $casts = [
        'environment_id' => 'integer',
        'resource_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Environment, covariant $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return MorphTo<Model, covariant $this>
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }
}
