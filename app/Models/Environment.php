<?php

namespace App\Models;

use App\Enums\EnvironmentStatus;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $application_id
 * @property ?int $primary_component_id
 * @property string $name
 * @property string $slug
 * @property ?string $branch
 * @property string $color
 * @property bool $auto_deploy
 * @property EnvironmentStatus $status
 * @property Application $application
 * @property ?Component $primaryComponent
 * @property Collection<int, Component> $components
 * @property Collection<int, EnvironmentDomain> $domains
 * @property Collection<int, EnvironmentVariable> $variables
 * @property Collection<int, EnvironmentDeployment> $environmentDeployments
 * @property Collection<int, EnvironmentResource> $resources
 * @property Collection<int, DeploymentScript> $deploymentScripts
 */
class Environment extends AbstractModel
{
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'primary_component_id',
        'name',
        'slug',
        'branch',
        'color',
        'auto_deploy',
        'status',
    ];

    protected $casts = [
        'application_id' => 'integer',
        'primary_component_id' => 'integer',
        'auto_deploy' => 'boolean',
        'status' => EnvironmentStatus::class,
    ];

    /**
     * @return BelongsTo<Application, covariant $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<Component, covariant $this>
     */
    public function primaryComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'primary_component_id');
    }

    /**
     * @return HasMany<Component, covariant $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * @return HasMany<EnvironmentDomain, covariant $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(EnvironmentDomain::class);
    }

    /**
     * Every domain in an environment lands on one component. That is the primary
     * component when it serves HTTP, and otherwise the first component that does — an
     * environment whose primary is a worker still answers on its web component.
     */
    public function servingComponent(): ?Component
    {
        $components = $this->relationLoaded('components')
            ? $this->components
            : $this->components()->with('functions')->get();

        $primary = $this->primary_component_id !== null
            ? $components->firstWhere('id', $this->primary_component_id)
            : null;

        if ($primary !== null && $primary->terminatesHttp()) {
            return $primary;
        }

        return $components->first(fn (Component $component): bool => $component->terminatesHttp());
    }

    /**
     * @return HasMany<EnvironmentVariable, covariant $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    /**
     * @return HasMany<EnvironmentDeployment, covariant $this>
     */
    public function environmentDeployments(): HasMany
    {
        return $this->hasMany(EnvironmentDeployment::class);
    }

    /**
     * @return HasMany<EnvironmentResource, covariant $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(EnvironmentResource::class);
    }

    /**
     * @return HasMany<DeploymentScript, covariant $this>
     */
    public function deploymentScripts(): HasMany
    {
        return $this->hasMany(DeploymentScript::class);
    }

    public function project(): Project
    {
        return $this->application->project;
    }
}
