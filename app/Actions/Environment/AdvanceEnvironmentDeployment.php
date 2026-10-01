<?php

namespace App\Actions\Environment;

use App\Enums\ComponentType;
use App\Enums\DeploymentStage;
use App\Enums\DeploymentStatus;
use App\Enums\EnvironmentDeploymentStatus;
use App\Enums\EnvironmentStatus;
use App\Jobs\Environment\DeployComponentStageJob;
use App\Models\Component;
use App\Models\Deployment;
use App\Models\EnvironmentDeployment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The staged barrier: no component starts a stage until every participant has cleared the one
 * before it, so a failure before `release` leaves nothing cut over.
 */
class AdvanceEnvironmentDeployment
{
    public function startStage(EnvironmentDeployment $run, DeploymentStage $stage): void
    {
        $run->current_stage = $stage;
        $run->save();

        $participants = $this->participants($run, $stage);

        if ($participants->isEmpty()) {
            $this->advanceFrom($run, $stage);

            return;
        }

        foreach ($participants as $deployment) {
            dispatch(new DeployComponentStageJob($deployment, $stage))->onQueue('ssh');
        }
    }

    public function componentFinished(Deployment $deployment, DeploymentStage $stage): void
    {
        $advance = DB::transaction(function () use ($deployment, $stage): bool {
            /** @var ?EnvironmentDeployment $run */
            $run = EnvironmentDeployment::query()
                ->whereKey($deployment->environment_deployment_id)
                ->lockForUpdate()
                ->first();

            if ($run === null || $run->status->isTerminal()) {
                return false;
            }

            $alreadyRecorded = $deployment->current_stage === $stage;

            if (! $alreadyRecorded) {
                $deployment->current_stage = $stage;
                $deployment->save();
            }

            $outstanding = $this->participants($run, $stage)
                ->reject(fn (Deployment $participant): bool => $participant->current_stage === $stage)
                ->count();

            // Idempotent: a repeated call for the same pair finds nothing outstanding but the run
            // has already moved on, so it dispatches nothing.
            return $outstanding === 0 && $run->current_stage === $stage;
        });

        if ($advance) {
            $run = $deployment->environmentDeployment->refresh();
            $this->advanceFrom($run, $stage);
        }
    }

    public function componentFailed(Deployment $deployment, string $error): void
    {
        $deployment->status = DeploymentStatus::FAILED;
        $deployment->save();
        $deployment->log?->write($error);

        $run = $deployment->environmentDeployment;

        if ($run === null || $run->status->isTerminal()) {
            return;
        }

        // Failing before `release` means nothing cut over; failing during it means some components
        // did and some did not, which is exactly what PARTIAL is for.
        $run->status = $run->current_stage === DeploymentStage::RELEASE
            ? EnvironmentDeploymentStatus::PARTIAL
            : EnvironmentDeploymentStatus::FAILED;
        $run->save();

        $this->writeEnvironmentStatus($run);
    }

    private function advanceFrom(EnvironmentDeployment $run, DeploymentStage $stage): void
    {
        $next = $stage->next();

        if ($next === null) {
            $run->status = EnvironmentDeploymentStatus::FINISHED;
            $run->save();
            $this->writeEnvironmentStatus($run);

            return;
        }

        $this->startStage($run, $next);
    }

    /**
     * A single component failure never fails the Environment itself — C11.
     */
    private function writeEnvironmentStatus(EnvironmentDeployment $run): void
    {
        $environment = $run->environment;

        $environment->status = $run->status === EnvironmentDeploymentStatus::FAILED
            ? EnvironmentStatus::FAILED
            : EnvironmentStatus::READY;
        $environment->save();
    }

    /**
     * @return Collection<int, Deployment>
     */
    private function participants(EnvironmentDeployment $run, DeploymentStage $stage): Collection
    {
        $deployments = $run->deployments()->with('component')->get();

        return match ($stage) {
            DeploymentStage::CLONE, DeploymentStage::BUILD => $deployments->filter(
                fn (Deployment $deployment): bool => $deployment->component?->type === ComponentType::APP
            )->values(),
            DeploymentStage::PRE_FLIGHT => $deployments->filter(
                fn (Deployment $deployment): bool => $deployment->component_id === $run->environment->primary_component_id
            )->values(),
            DeploymentStage::RELEASE => $deployments->values(),
        };
    }
}
