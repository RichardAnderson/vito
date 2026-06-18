<?php

namespace App\Jobs\Site;

use App\Actions\Site\BroadcastSiteUpdate;
use App\Actions\Site\RestoreDeploymentBackup;
use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Traits\UniqueQueue;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreDeploymentBackupJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(
        protected Deployment $deployment
    ) {
        $this->onQueue('ssh');
    }

    public function handle(): void
    {
        $site = $this->deployment->site;

        $this->run("site-{$site->id}", function () use ($site): void {
            app(RestoreDeploymentBackup::class)->execute($this->deployment);

            SocketEvent::dispatch(new SocketEventDTO(
                projectId: $site->server->project_id,
                type: 'deployment.updated',
                data: new DeploymentResource($this->deployment),
            ));
            app(BroadcastSiteUpdate::class)->broadcast($site);
        });
    }

    public function failed(Exception $e): void
    {
        $site = $this->deployment->site;

        $this->deployment->log?->write("Backup restore failed: {$e->getMessage()}");

        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $site->server->project_id,
            type: 'deployment.updated',
            data: new DeploymentResource($this->deployment),
        ));
        app(BroadcastSiteUpdate::class)->broadcast($site);
    }
}
