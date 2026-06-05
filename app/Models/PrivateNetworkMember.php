<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\PrivateNetworkMemberFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $private_network_id
 * @property int $server_id
 * @property string $overlay_ip
 * @property string $interface
 * @property int $listen_port
 * @property ?string $public_key
 * @property ?string $endpoint
 * @property MemberStatus $status
 * @property ?array<string, mixed> $meta
 * @property ?string $error
 * @property PrivateNetwork $privateNetwork
 * @property Server $server
 * @property Collection<int, FirewallRule> $firewallRules
 */
class PrivateNetworkMember extends AbstractModel
{
    /** @use HasFactory<PrivateNetworkMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'private_network_id',
        'server_id',
        'overlay_ip',
        'interface',
        'listen_port',
        'public_key',
        'endpoint',
        'status',
        'meta',
        'error',
    ];

    protected $casts = [
        'private_network_id' => 'integer',
        'server_id' => 'integer',
        'listen_port' => 'integer',
        'status' => MemberStatus::class,
        'meta' => 'json',
    ];

    /**
     * @return BelongsTo<PrivateNetwork, covariant $this>
     */
    public function privateNetwork(): BelongsTo
    {
        return $this->belongsTo(PrivateNetwork::class);
    }

    /**
     * @return BelongsTo<Server, covariant $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<FirewallRule, covariant $this>
     */
    public function firewallRules(): HasMany
    {
        return $this->hasMany(FirewallRule::class);
    }
}
