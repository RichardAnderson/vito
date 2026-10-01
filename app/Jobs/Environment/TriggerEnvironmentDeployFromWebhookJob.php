<?php

namespace App\Jobs\Environment;

use App\Actions\Environment\DeployEnvironment;
use App\Models\Environment;
use App\Models\ServerLog;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * A push webhook must not deploy synchronously inside the request.
 */
class TriggerEnvironmentDeployFromWebhookJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(protected Environment $environment) {}

    public function handle(): void
    {
        $this->run("environment-{$this->environment->id}", function (): void {
            app(DeployEnvironment::class)->run($this->environment);
        });
    }

    public function failed(Throwable $e): void
    {
        $component = $this->environment->components()->first();

        if ($component !== null) {
            ServerLog::log($component->server, 'auto-deploy-failed', $e->getMessage());
        }
    }
}
