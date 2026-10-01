<?php

namespace App\Models;

use App\Enums\ComponentStatus;
use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use Database\Factories\ComponentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $environment_id
 * @property int $server_id
 * @property ?int $isolated_user_id
 * @property string $name
 * @property ComponentType $type
 * @property ?string $path
 * @property ?string $web_directory
 * @property ?string $php_version
 * @property ?array<string, mixed> $type_data
 * @property ComponentStatus $status
 * @property ?string $last_error
 * @property bool $needs_redeploy
 * @property ?string $user
 * @property Environment $environment
 * @property Server $server
 * @property ?IsolatedUser $isolatedUser
 * @property Collection<int, ComponentFunction> $functions
 * @property Collection<int, ComponentBackend> $backends
 * @property Collection<int, Worker> $workers
 * @property Collection<int, CronJob> $cronJobs
 * @property Collection<int, Deployment> $deployments
 * @property Collection<int, EnvironmentVariable> $variableOverrides
 */
class Component extends AbstractModel
{
    /** @use HasFactory<ComponentFactory> */
    use HasFactory;

    protected $fillable = [
        'environment_id',
        'server_id',
        'isolated_user_id',
        'name',
        'type',
        'path',
        'web_directory',
        'php_version',
        'type_data',
        'status',
        'last_error',
        'needs_redeploy',
    ];

    protected $casts = [
        'environment_id' => 'integer',
        'server_id' => 'integer',
        'isolated_user_id' => 'integer',
        'type_data' => 'array',
        'needs_redeploy' => 'boolean',
        'type' => ComponentType::class,
        'status' => ComponentStatus::class,
    ];

    /**
     * @return BelongsTo<Environment, covariant $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return BelongsTo<Server, covariant $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<IsolatedUser, covariant $this>
     */
    public function isolatedUser(): BelongsTo
    {
        return $this->belongsTo(IsolatedUser::class);
    }

    /**
     * @return HasMany<ComponentFunction, covariant $this>
     */
    public function functions(): HasMany
    {
        return $this->hasMany(ComponentFunction::class);
    }

    /**
     * @return HasMany<ComponentBackend, covariant $this>
     */
    public function backends(): HasMany
    {
        return $this->hasMany(ComponentBackend::class);
    }

    /**
     * @return HasMany<Worker, covariant $this>
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    /**
     * @return HasMany<CronJob, covariant $this>
     */
    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }

    /**
     * @return HasMany<Deployment, covariant $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<EnvironmentVariable, covariant $this>
     */
    public function variableOverrides(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    public function getUserAttribute(?string $value): ?string
    {
        return ($value !== null && $value !== '') ? $value : $this->isolatedUser?->username;
    }

    public function basePath(): string
    {
        $environment = $this->environment;

        return '/home/'.$this->user.'/app-'.$environment->application_id.'-'.$environment->slug;
    }

    public function currentPath(): string
    {
        return $this->basePath().'/current';
    }

    public function hasFunction(string $function): bool
    {
        return $this->functions->contains(fn (ComponentFunction $row): bool => $row->function === $function);
    }

    public function terminatesHttp(): bool
    {
        return $this->type->terminatesHttp() || $this->hasFunction(ComponentFunctions::HTTP);
    }

    public function project(): Project
    {
        return $this->environment->application->project;
    }
}
