<?php

namespace App\Actions\Environment;

use App\Actions\EnvironmentDomain\DeleteEnvironmentDomain;
use App\Enums\EnvironmentStatus;
use App\Jobs\Component\DeleteComponentJob;
use App\Jobs\Environment\DeleteParentRowJob;
use App\Models\Environment;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class DeleteEnvironment
{
    /**
     * The parent row must outlive the teardown jobs: `components.environment_id` is
     * `cascadeOnDelete`, so deleting it first destroys the rows the queued jobs need. A chain
     * puts the parent delete last without needing a `job_batches` table.
     */
    public function delete(Environment $environment): void
    {
        foreach ($environment->domains()->get() as $domain) {
            app(DeleteEnvironmentDomain::class)->delete($domain, force: true);
        }

        $environment->resources()->delete();

        $environment->status = EnvironmentStatus::DELETING;
        $environment->save();

        $jobs = $environment->components()->get()
            ->map(fn ($component): DeleteComponentJob => new DeleteComponentJob($component->id, $component->server_id))
            ->all();

        $jobs[] = new DeleteParentRowJob('environment', $environment->id);

        DB::afterCommit(fn () => Bus::chain($jobs)->onQueue('ssh')->dispatch());
    }
}
