<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\PrivateNetworkStatus;
use Database\Factories\PrivateNetworkFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $project_id
 * @property string $name
 * @property string $subnet
 * @property int $mtu
 * @property PrivateNetworkStatus $status
 * @property Project $project
 * @property Collection<int, PrivateNetworkMember> $members
 * @property Collection<int, Server> $servers
 */
class PrivateNetwork extends AbstractModel
{
    /** @use HasFactory<PrivateNetworkFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'subnet',
        'mtu',
        'status',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'mtu' => 'integer',
        'status' => PrivateNetworkStatus::class,
    ];

    /**
     * @return BelongsTo<Project, covariant $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<PrivateNetworkMember, covariant $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(PrivateNetworkMember::class);
    }

    /**
     * @return BelongsToMany<Server, covariant $this>
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'private_network_members')
            ->withPivot(['overlay_ip', 'interface', 'listen_port', 'public_key', 'endpoint', 'status'])
            ->withTimestamps();
    }

    public function prefix(): int
    {
        return (int) (explode('/', $this->subnet)[1] ?? 32);
    }

    /**
     * @return Collection<int, PrivateNetworkMember>
     */
    public function renderablePeers(int $excludeServerId): Collection
    {
        return $this->members()
            ->whereNotNull('public_key')
            ->where('status', MemberStatus::ACTIVE)
            ->where('server_id', '!=', $excludeServerId)
            ->with('server')
            ->get();
    }
}
