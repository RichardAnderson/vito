<?php

namespace App\Jobs\Environment;

use App\Actions\Component\SyncComponentVhost;
use App\Actions\Component\WriteComponentEnv;
use App\Actions\Environment\AdvanceEnvironmentDeployment;
use App\Actions\Environment\ResolveComponentVariables;
use App\Enums\ComponentType;
use App\Enums\DeploymentStage;
use App\Enums\DeploymentStatus;
use App\Exceptions\SSHError;
use App\Models\Component;
use App\Models\Deployment;
use App\Models\DeploymentScript;
use App\Models\Service;
use App\Services\ProcessManager\ProcessManager;
use App\SSH\OS\Git;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeployComponentStageJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected Deployment $deployment, protected DeploymentStage $stage) {}

    /**
     * @throws SSHError
     */
    public function handle(): void
    {
        $component = $this->deployment->component;

        if ($component === null) {
            return;
        }

        $completed = false;

        $this->run("server-{$component->server_id}", function () use ($component, &$completed): void {
            match ($this->stage) {
                DeploymentStage::CLONE => $this->cloneStage($component),
                DeploymentStage::BUILD => $this->scriptStage($component, 'build'),
                DeploymentStage::PRE_FLIGHT => $this->scriptStage($component, 'pre-flight'),
                DeploymentStage::RELEASE => $this->releaseStage($component),
            };

            $completed = true;
        });

        // The coordinator dispatches the next stage for the same server, so it must run after the
        // lock is released: a nested dispatch would fail the lock and release(30) into a no-op.
        if ($completed) {
            app(AdvanceEnvironmentDeployment::class)->componentFinished($this->deployment, $this->stage);
        }
    }

    public function failed(Throwable $e): void
    {
        app(AdvanceEnvironmentDeployment::class)->componentFailed(
            $this->deployment,
            'Stage '.$this->stage->value.' failed. See the deployment log for details.'
        );
    }

    /**
     * @throws SSHError
     */
    private function cloneStage(Component $component): void
    {
        $run = $this->deployment->environmentDeployment;

        // Stamping is an update, which fires `updated`, not `created` — so pruning is explicit.
        $this->deployment->release = $run?->release;
        $this->deployment->save();
        $this->deployment->pruneOldReleases();

        $application = $component->environment->application;
        $releasePath = $this->deployment->path();

        if ($application->source_control_id !== null && $application->repository !== null) {
            app(Git::class)->cloneRepository(
                server: $component->server,
                user: (string) $component->user,
                repoUrl: (string) $application->sourceControl?->provider()?->fullRepoUrl(
                    $application->repository,
                    'component_'.$component->id
                ),
                branch: (string) $component->environment->branch,
                path: $releasePath,
                keyName: 'component_'.$component->id,
                port: $application->sourceControl?->provider()?->getSshPort() ?? 22,
                usesToken: $application->sourceControl?->isGithubApp() ?? false,
            );
        }

        app(WriteComponentEnv::class)->write($component, $releasePath);
    }

    /**
     * @throws SSHError
     */
    private function scriptStage(Component $component, string $name): void
    {
        $script = $this->effectiveScript($component, $name);

        if ($script === null || trim((string) $script->content) === '') {
            return;
        }

        $component->server->os()->runScript(
            path: $this->deployment->path(),
            script: (string) $script->content,
            serverLog: $this->deployment->log,
            user: (string) $component->user,
            variables: $this->variables($component),
        );
    }

    /**
     * @throws SSHError
     */
    private function releaseStage(Component $component): void
    {
        if ($component->type === ComponentType::APP) {
            $component->server->ssh((string) $component->user)->exec(
                view('ssh.modern-deployment.release', [
                    'basePath' => $component->basePath(),
                    'releasePath' => $this->deployment->path(),
                ]),
                'release'
            );

            $this->restartWorkers($component);
        } else {
            // A load balancer or proxy takes part as config regeneration, not code deploy.
            app(SyncComponentVhost::class)->sync($component);
            $this->pruneConfigOnlyDeployments($component);
        }

        $this->deployment->status = DeploymentStatus::FINISHED;
        $this->deployment->save();
        $this->deployment->activate();

        $component->needs_redeploy = false;
        $component->save();
    }

    /**
     * @throws SSHError
     */
    private function restartWorkers(Component $component): void
    {
        $workerIds = $component->workers()->pluck('id')->all();

        if ($workerIds === []) {
            return;
        }

        $service = $component->server->processManager();

        if (! $service instanceof Service) {
            return;
        }

        /** @var ProcessManager $processManager */
        $processManager = $service->handler();
        $processManager->restartMany($workerIds);
    }

    /**
     * Config-only components never carry a `release`, so the release-keyed pruning never sees
     * them; they are pruned by run count instead.
     */
    private function pruneConfigOnlyDeployments(Component $component): void
    {
        $keep = 10;

        $ids = $component->deployments()
            ->whereNull('release')
            ->orderByDesc('id')
            ->offset($keep)
            ->limit(1000)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            Deployment::query()->whereIn('id', $ids)->delete();
        }
    }

    private function effectiveScript(Component $component, string $name): ?DeploymentScript
    {
        $environment = $component->environment;

        return $environment->deploymentScripts()
            ->where('component_id', $component->id)
            ->where('name', $name)
            ->first()
            ?? $environment->deploymentScripts()
                ->whereNull('component_id')
                ->where('name', $name)
                ->first();
    }

    /**
     * @return array<string, string>
     */
    private function variables(Component $component): array
    {
        $variables = [];

        foreach (app(ResolveComponentVariables::class)->resolve($component) as $key => $entry) {
            $variables[$key] = (string) $entry['value'];
        }

        return $variables;
    }
}
