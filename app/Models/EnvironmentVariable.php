<?php

namespace App\Models;

use Database\Factories\EnvironmentVariableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $environment_id
 * @property ?int $component_id
 * @property string $key
 * @property ?string $value
 * @property bool $is_secret
 * @property Environment $environment
 * @property ?Component $component
 */
class EnvironmentVariable extends AbstractModel
{
    /** @use HasFactory<EnvironmentVariableFactory> */
    use HasFactory;

    protected $fillable = [
        'environment_id',
        'component_id',
        'key',
        'value',
        'is_secret',
    ];

    protected $casts = [
        'environment_id' => 'integer',
        'component_id' => 'integer',
        'value' => 'encrypted',
        'is_secret' => 'boolean',
    ];

    /** @var list<string> */
    protected $hidden = ['value'];

    /**
     * @return BelongsTo<Environment, covariant $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
