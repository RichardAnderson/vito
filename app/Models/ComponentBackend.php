<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $component_id
 * @property int $target_component_id
 * @property ?int $network_server_id
 * @property int $port
 * @property Component $component
 * @property Component $targetComponent
 * @property ?NetworkServer $networkServer
 */
class ComponentBackend extends AbstractModel
{
    protected $fillable = [
        'component_id',
        'target_component_id',
        'network_server_id',
        'port',
    ];

    protected $casts = [
        'component_id' => 'integer',
        'target_component_id' => 'integer',
        'network_server_id' => 'integer',
        'port' => 'integer',
    ];

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function targetComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'target_component_id');
    }

    /**
     * @return BelongsTo<NetworkServer, covariant $this>
     */
    public function networkServer(): BelongsTo
    {
        return $this->belongsTo(NetworkServer::class);
    }
}
