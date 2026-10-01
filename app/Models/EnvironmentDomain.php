<?php

namespace App\Models;

use App\Enums\EnvironmentDomainStatus;
use App\Enums\EnvironmentDomainType;
use Database\Factories\EnvironmentDomainFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $environment_id
 * @property ?int $ssl_id
 * @property ?int $redirect_to_domain_id
 * @property string $host
 * @property EnvironmentDomainType $type
 * @property string $ssl_method
 * @property EnvironmentDomainStatus $status
 * @property Environment $environment
 * @property ?Ssl $ssl
 * @property ?EnvironmentDomain $redirectTo
 * @property Collection<int, EnvironmentDomain> $redirectedBy
 */
class EnvironmentDomain extends AbstractModel
{
    /** @use HasFactory<EnvironmentDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'environment_id',
        'ssl_id',
        'redirect_to_domain_id',
        'host',
        'type',
        'ssl_method',
        'status',
    ];

    protected $casts = [
        'environment_id' => 'integer',
        'ssl_id' => 'integer',
        'redirect_to_domain_id' => 'integer',
        'type' => EnvironmentDomainType::class,
        'status' => EnvironmentDomainStatus::class,
    ];

    public function url(): string
    {
        return ($this->ssl_id !== null ? 'https' : 'http').'://'.$this->host;
    }

    public function isAlias(): bool
    {
        return $this->type === EnvironmentDomainType::ALIAS;
    }

    public function isRedirect(): bool
    {
        return $this->type === EnvironmentDomainType::REDIRECT;
    }

    /**
     * @return BelongsTo<Environment, covariant $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Ssl, covariant $this>
     */
    public function ssl(): BelongsTo
    {
        return $this->belongsTo(Ssl::class);
    }

    /**
     * @return BelongsTo<EnvironmentDomain, covariant $this>
     */
    public function redirectTo(): BelongsTo
    {
        return $this->belongsTo(EnvironmentDomain::class, 'redirect_to_domain_id');
    }

    /**
     * @return HasMany<EnvironmentDomain, covariant $this>
     */
    public function redirectedBy(): HasMany
    {
        return $this->hasMany(EnvironmentDomain::class, 'redirect_to_domain_id');
    }

    /**
     * Every domain in an environment is served by that environment's serving component, so
     * exactly one server terminates it.
     */
    public function terminatingComponent(): ?Component
    {
        return $this->environment->servingComponent();
    }

    public function isWildcard(): bool
    {
        return str_starts_with($this->host, '*.');
    }

    public function project(): Project
    {
        return $this->environment->application->project;
    }
}
