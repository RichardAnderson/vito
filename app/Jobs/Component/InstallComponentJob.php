<?php

namespace App\Jobs\Component;

use App\Actions\Component\SyncComponentVhost;
use App\Actions\Environment\ResolveComponentVariables;
use App\Actions\Worker\ProvisionWorker;
use App\DTOs\SocketEventDTO;
use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\EnvironmentDomainStatus;
use App\Enums\ComponentType;
use App\Enums\CronjobStatus;
use App\Enums\WorkerStatus;
use App\Events\SocketEvent;
use App\Exceptions\RepositoryNotFound;
use App\Exceptions\RepositoryPermissionDenied;
use App\Exceptions\SourceControlIsNotConnected;
use App\Exceptions\SSHCommandError;
use App\Exceptions\SSHConnectionError;
use App\Exceptions\SSHError;
use App\Jobs\EnvironmentDomain\SyncEnvironmentDomainJob;
use App\Http\Resources\ComponentResource;
use App\Models\Component;
use App\Models\CronJob;
use App\Models\Service;
use App\Models\Worker;
use App\Services\PHP\PHP;
use App\SSH\OS\Git;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InstallComponentJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected Component $component) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $this->run("server-{$this->component->server_id}", function (): void {
            $component = $this->component;
            $component->status = ComponentStatus::INSTALLING;
            $component->save();

            $this->createIsolatedUser($component);
            $this->createFpmPool($component);

            if ($component->type->terminatesHttp()) {
                app(SyncComponentVhost::class)->sync($component);
            } else {
                $this->prepareCheckout($component);

                if ($component->hasFunction(ComponentFunctions::HTTP)) {
                    app(SyncComponentVhost::class)->sync($component);
                }

                $this->createScheduler($component);
            }

            $component->status = ComponentStatus::READY;
            $component->last_error = null;
            $component->save();

            $this->broadcast($component);
        });

        // Domains can be registered before the environment has anywhere to serve them, so the
        // ones parked as PENDING are synced here. SyncEnvironmentDomainJob takes the same server
        // lock, so it is dispatched only after run() has released it.
        $this->syncPendingDomains();
    }

    public function failed(Throwable $e): void
    {
        $this->component->status = ComponentStatus::INSTALLATION_FAILED;
        $this->component->last_error = $this->safeErrorSummary($e);
        $this->component->save();

        $this->broadcast($this->component);
    }

    private function syncPendingDomains(): void
    {
        $component = $this->component->fresh();

        if ($component === null || $component->environment->servingComponent()?->id !== $component->id) {
            return;
        }

        $pending = $component->environment->domains()
            ->where('status', EnvironmentDomainStatus::PENDING)
            ->get();

        foreach ($pending as $domain) {
            dispatch(new SyncEnvironmentDomainJob($domain))->onQueue('ssh');
        }
    }

    /**
     * @throws SSHError
     */
    private function createIsolatedUser(Component $component): void
    {
        $isolatedUser = $component->isolatedUser;

        if ($isolatedUser === null) {
            return;
        }

        $lock = $isolatedUser->lock();

        if (! $lock->block(30, fn (): bool => true)) {
            throw new RuntimeException("Could not acquire isolated-user lock for '{$isolatedUser->username}' on server {$component->server_id} within 30s.");
        }

        try {
            $component->server->os()->createIsolatedUser($isolatedUser->username, Str::random(15));
        } finally {
            $lock->release();
        }
    }

    /**
     * `fpmPoolExists()` is already true when a sibling Site or Component owns the pool, so it is
     * the only guard the create path needs.
     *
     * @throws SSHError
     */
    private function createFpmPool(Component $component): void
    {
        if ($component->php_version === null || $component->user === null) {
            return;
        }

        $service = $component->server->php($component->php_version);

        if (! $service instanceof Service) {
            throw new RuntimeException('PHP service not found');
        }

        if ($this->fpmPoolExists($component, $component->user, $component->php_version)) {
            return;
        }

        /** @var PHP $php */
        $php = $service->handler();
        $php->createFpmPool($component->user, $component->php_version);
    }

    /**
     * @throws SSHError
     */
    private function fpmPoolExists(Component $component, string $user, string $version): bool
    {
        try {
            $component->server->ssh()->exec(view('ssh.site.check-fpm-pool-exists', [
                'user' => $user,
                'version' => $version,
            ]));

            return true;
        } catch (SSHCommandError) {
            return false;
        }
    }

    /**
     * @throws SSHError
     */
    private function prepareCheckout(Component $component): void
    {
        $server = $component->server;
        $user = (string) $component->user;
        $basePath = $component->basePath();

        $server->ssh($user)->exec('mkdir -p '.escapeshellarg($basePath.'/releases'), 'create-component-path');

        $application = $component->environment->application;

        if ($application->source_control_id === null || $application->repository === null) {
            return;
        }

        $releasePath = $basePath.'/releases/initial';

        app(Git::class)->cloneRepository(
            server: $server,
            user: $user,
            repoUrl: (string) $application->sourceControl?->provider()?->fullRepoUrl(
                $application->repository,
                $this->sshKeyName($component)
            ),
            branch: (string) $component->environment->branch,
            path: $releasePath,
            keyName: $this->sshKeyName($component),
            port: $application->sourceControl?->provider()?->getSshPort() ?? 22,
            usesToken: $application->sourceControl?->isGithubApp() ?? false,
        );

        $server->ssh($user)->exec(
            view('ssh.modern-deployment.release', [
                'basePath' => $basePath,
                'releasePath' => $releasePath,
            ]),
            'release'
        );
    }

    private function sshKeyName(Component $component): string
    {
        return 'component_'.$component->id;
    }

    /**
     * @throws SSHError
     */
    private function createScheduler(Component $component): void
    {
        if (! $component->hasFunction(ComponentFunctions::SCHEDULER)) {
            return;
        }

        $server = $component->server;
        $user = (string) $component->user;

        CronJob::query()->create([
            'server_id' => $server->id,
            'component_id' => $component->id,
            'command' => 'cd '.$component->currentPath().' && php artisan schedule:run',
            'user' => $user,
            'frequency' => '* * * * *',
            'status' => CronjobStatus::READY,
        ]);

        $server->cron()->update($user, CronJob::crontab($server, $user));
    }

    private function broadcast(Component $component): void
    {
        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $component->server->project_id,
            type: 'component.updated',
            data: new ComponentResource($component->load(['server', 'functions'])),
        ));
    }

    private function safeErrorSummary(Throwable $e): string
    {
        $messages = [
            SSHCommandError::class => 'An SSH command failed during installation. Check the server logs for the failing command.',
            SSHConnectionError::class => 'Could not connect to the server over SSH. Verify the server is reachable and try again.',
            RepositoryNotFound::class => 'Repository not found on the source control provider.',
            RepositoryPermissionDenied::class => 'Permission denied accessing the repository on the source control provider.',
            SourceControlIsNotConnected::class => 'Source control provider is not connected.',
        ];

        foreach ($messages as $class => $message) {
            if ($e instanceof $class) {
                return $message;
            }
        }

        return 'Installation failed due to an unexpected error. See the server logs for full details.';
    }
}
