<?php

namespace App\Models;

use App\Enums\FirewallRuleStatus;
use Database\Factories\FirewallRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $server_id
 * @property ?int $private_network_member_id
 * @property string $name
 * @property string $type
 * @property ?string $protocol
 * @property ?string $port
 * @property string $source
 * @property ?string $mask
 * @property string $note
 * @property FirewallRuleStatus $status
 * @property Server $server
 * @property ?PrivateNetworkMember $privateNetworkMember
 */
class FirewallRule extends AbstractModel
{
    /** @use HasFactory<FirewallRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'server_id',
        'type',
        'protocol',
        'port',
        'source',
        'mask',
        'note',
        'status',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'private_network_member_id' => 'integer',
        'status' => FirewallRuleStatus::class,
    ];

    /**
     * @return BelongsTo<Server, covariant $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<PrivateNetworkMember, covariant $this>
     */
    public function privateNetworkMember(): BelongsTo
    {
        return $this->belongsTo(PrivateNetworkMember::class);
    }
}
