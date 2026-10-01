<?php

namespace App\Actions\Environment;

use App\Jobs\Environment\TriggerEnvironmentDeployFromWebhookJob;
use App\Models\Environment;

/**
 * The webhook receiver is a controller, and controllers do not dispatch jobs.
 */
class TriggerAutoDeploy
{
    public function forBranch(Environment $environment, string $branch): bool
    {
        if ($environment->branch !== $branch) {
            return false;
        }

        dispatch(new TriggerEnvironmentDeployFromWebhookJob($environment))->onQueue('ssh');

        return true;
    }
}
