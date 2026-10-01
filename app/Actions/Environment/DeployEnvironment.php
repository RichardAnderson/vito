<?php

namespace App\Actions\Environment;

use App\Support\ComponentFunctions;
use App\Enums\ComponentStatus;
use App\Enums\ComponentType;
use App\Enums\DeploymentStage;
use App\Enums\DeploymentStatus;
use App\Enums\EnvironmentDeploymentStatus;
use App\Enums\EnvironmentStatus;
use App\Models\Component;
use App\Models\Deployment;
use App\Models\Environment;
use App\Models\EnvironmentDeployment;
use App\Models\ServerLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeployEnvironment
{
    /**
     * @throws ValidationException
     */
    public function run(Environment $environment): EnvironmentDeployment
    {
        $components = $environment->components()->where('status', ComponentStatus::READY)->get();

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'environment' => 'This environment has no ready components to deploy.',
            ]);
        }

        $this->ensurePrimaryIsAnAppComponent($environment, $components);

        $application = $environment->application;
        $commitId = $application->sourceControl?->provider()?->getLastCommit(
            (string) $application->repository,
            (string) $environment->branch
        );

        $environment->status = EnvironmentStatus::DEPLOYING;
        $environment->save();

        $run = DB::transaction(function () use ($environment, $components, $commitId): EnvironmentDeployment {
            $run = new EnvironmentDeployment([
                'environment_id' => $environment->id,
                'commit_id' => is_array($commitId) ? ($commitId['commit_id'] ?? null) : $commitId,
                'commit_data' => is_array($commitId) ? $commitId : null,
                'status' => EnvironmentDeploymentStatus::RUNNING,
                'current_stage' => DeploymentStage::CLONE,
                // One shared release for the whole fan-out, stamped onto each Deployment by the
                // clone stage rather than at insert — the pruning hook fires on `created`.
                'release' => Carbon::now()->format('YmdHis'),
            ]);
            $run->save();

            foreach ($components as $component) {
                $deployment = new Deployment([
                    'site_id' => null,
                    'component_id' => $component->id,
                    'environment_deployment_id' => $run->id,
                    'deployment_script_id' => $this->buildScriptId($environment, $component),
                    'commit_id' => $run->commit_id,
                    'commit_data' => $run->commit_data,
                    'status' => DeploymentStatus::DEPLOYING,
                    'release' => null,
                ]);
                $deployment->log_id = ServerLog::log($component->server, 'deploy-component', '')->id;
                $deployment->save();
            }

            return $run;
        });

        DB::afterCommit(fn () => app(AdvanceEnvironmentDeployment::class)->startStage($run, DeploymentStage::CLONE));

        return $run;
    }

    /**
     * A load balancer has no clone stage, no release directory and a null deployment_script_id, so
     * a primary that is not an app component fails every deploy before release.
     *
     * @param  \Illuminate\Support\Collection<int, Component>  $components
     */
    private function ensurePrimaryIsAnAppComponent(Environment $environment, $components): void
    {
        $stored = $environment->primary_component_id !== null
            ? $components->firstWhere('id', $environment->primary_component_id)
            : null;

        if ($stored !== null && $stored->type === ComponentType::APP) {
            return;
        }

        $appComponents = $components->filter(fn (Component $component): bool => $component->type === ComponentType::APP);

        $primary = $appComponents->first(fn (Component $component): bool => $component->hasFunction(ComponentFunctions::HTTP))
            ?? $appComponents->first();

        if ($primary === null) {
            throw ValidationException::withMessages([
                'environment' => 'This environment has no app component to run the pre-flight stage.',
            ]);
        }

        $environment->primary_component_id = $primary->id;
        $environment->save();
    }

    private function buildScriptId(Environment $environment, Component $component): ?int
    {
        if ($component->type !== ComponentType::APP) {
            return null;
        }

        $override = $environment->deploymentScripts()
            ->where('component_id', $component->id)
            ->where('name', 'build')
            ->value('id');

        return $override ?? $environment->deploymentScripts()
            ->whereNull('component_id')
            ->where('name', 'build')
            ->value('id');
    }
}
