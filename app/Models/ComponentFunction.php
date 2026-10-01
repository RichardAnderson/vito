<?php

namespace App\Models;

use App\Enums\ComponentStatus;
use App\Support\ComponentFunctions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $component_id
 * @property string $function
 * @property ?array<string, mixed> $config
 * @property ComponentStatus $status
 * @property Component $component
 */
class ComponentFunction extends AbstractModel
{
    protected $table = 'component_functions';

    protected $fillable = [
        'component_id',
        'function',
        'config',
        'status',
    ];

    protected $casts = [
        'component_id' => 'integer',
        'config' => 'array',
        'status' => ComponentStatus::class,
    ];

    public function label(): string
    {
        return ComponentFunctions::label($this->function);
    }

    public function color(): string
    {
        return ComponentFunctions::color($this->function);
    }

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
