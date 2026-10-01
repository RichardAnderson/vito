<?php

namespace App\Actions\Application;

use App\Actions\EnvironmentDomain\DeleteEnvironmentDomain;
use App\DTOs\SocketEventDTO;
use App\Enums\ApplicationStatus;
use App\Enums\EnvironmentStatus;
use App\Events\SocketEvent;
use App\Http\Resources\ApplicationResource;
use App\Jobs\Component\DeleteComponentJob;
use App\Jobs\Environment\DeleteParentRowJob;
use App\Models\Application;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DeleteApplication
{
    /**
     * Attached resources are detached, never deleted.
     */
    public function delete(Application $application): void
    {
        $application->status = ApplicationStatus::DELETING;
        $application->save();

        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $application->project_id,
            type: 'application.updated',
            data: new ApplicationResource($application),
        ));

        $jobs = [];

        foreach ($application->environments()->get() as $environment) {
            foreach ($environment->domains()->get() as $domain) {
                app(DeleteEnvironmentDomain::class)->delete($domain, force: true);
            }

            $environment->resources()->delete();

            $environment->status = EnvironmentStatus::DELETING;
            $environment->save();

            foreach ($environment->components()->get() as $component) {
                $jobs[] = new DeleteComponentJob($component->id, $component->server_id);
            }
        }

        $jobs[] = new DeleteParentRowJob('application', $application->id);

        DB::afterCommit(fn () => Bus::chain($jobs)->onQueue('ssh')->dispatch());
    }
}
