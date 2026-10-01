<?php

namespace App\Actions\Worker;

use App\Actions\Site\BroadcastSiteUpdate;
use App\Enums\WorkerStatus;
use App\Exceptions\SSHError;
use App\Models\Service;
use App\Models\Worker;
use App\Services\ProcessManager\ProcessManager;
use App\Traits\HandlesWorkerFailure;

/**
 * The body of Jobs\Worker\CreateJob, extracted so a caller already holding the
 * `server-{id}` lock can provision inline instead of dispatching a job onto the key it holds.
 */
class ProvisionWorker
{
    use HandlesWorkerFailure;

    /**
     * @throws SSHError
     */
    public function provision(Worker $worker): void
    {
        /** @var Service $service */
        $service = $worker->server->processManager();
        /** @var ProcessManager $processManager */
        $processManager = $service->handler();
        $processManager->create($worker);

        $worker->status = WorkerStatus::RUNNING;
        $worker->error = null;
        $worker->save();

        $this->broadcastWorkerUpdate($worker);

        if ($worker->site) {
            app(BroadcastSiteUpdate::class)->broadcast($worker->site);
        }
    }

    public function markFailed(Worker $worker, \Throwable $e): void
    {
        $this->markWorkerFailed($worker, $e, 'create-worker-failed');
    }
}
