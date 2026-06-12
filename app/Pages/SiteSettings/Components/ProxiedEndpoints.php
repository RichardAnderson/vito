<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\UpdatePort;
use App\Actions\Site\UpdateSiteWorkerEnvironment;
use App\Actions\Site\UpdateStartCommand;
use App\Actions\Site\WorkerStartCommandUpdateResult;
use App\Actions\Worker\WorkerEnvironmentUpdateResult;
use App\Helpers\EnvParser;
use App\Models\Site;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\SiteTypes\AbstractProxiedSiteType;
use Illuminate\Http\RedirectResponse;

/**
 * Proxied-site endpoints (port, worker start command, worker environment). These have
 * no row on the Settings page — they are driven from the Application page — so they
 * are exposed as headless actions and a data endpoint guarded to proxied sites.
 */
final class ProxiedEndpoints extends Section
{
    public function headless(): array
    {
        return [
            PageAction::make('update-port')->patch()
                ->run(fn (Site $site, array $input) => app(UpdatePort::class)->update($site, $input))
                ->success('Port updated and VHost regenerated.'),

            PageAction::make('update-start-command')->patch()
                ->run(fn (Site $site, array $input) => $this->startCommandFlash(app(UpdateStartCommand::class)->update($site, $input))),

            PageAction::make('update-worker-env')->patch()
                ->run(function (Site $site, array $input) {
                    $this->assertProxied($site);

                    return $this->workerEnvFlash(app(UpdateSiteWorkerEnvironment::class)->update($site, $input));
                }),

            DataEndpoint::make('worker-env')->public()
                ->resolve(function (Site $site): array {
                    $this->assertProxied($site);

                    /** @var AbstractProxiedSiteType $type */
                    $type = $site->type();

                    return ['variables' => EnvParser::maskSecrets($type->bootstrapWorker()->environment ?? $site->worker_environment ?? [])];
                }),
        ];
    }

    private function assertProxied(Site $site): void
    {
        abort_unless($site->type() instanceof AbstractProxiedSiteType, 404);
    }

    private function startCommandFlash(WorkerStartCommandUpdateResult $result): RedirectResponse
    {
        return match ($result) {
            WorkerStartCommandUpdateResult::PreFirstDeploy => back()->with('info', 'Start command saved. It will be used when the site is first deployed.'),
            WorkerStartCommandUpdateResult::PendingRestart => back()->with('warning', 'Start command updated. The worker is still running with the previous command — restart the worker or deploy to apply.'),
            WorkerStartCommandUpdateResult::Restarting => back()->with('info', 'Start command updated. The worker is restarting to apply the change.'),
        };
    }

    private function workerEnvFlash(WorkerEnvironmentUpdateResult $result): RedirectResponse
    {
        return match ($result) {
            WorkerEnvironmentUpdateResult::PreFirstDeploy => back()->with('info', 'Environment saved. It will be applied when the application worker is created on the first deploy.'),
            WorkerEnvironmentUpdateResult::PendingRestart => back()->with('warning', 'Environment updated. The worker is still running with the previous variables — restart it or deploy to apply.'),
            WorkerEnvironmentUpdateResult::Restarting => back()->with('info', 'Environment updated. The worker is restarting to apply the change.'),
        };
    }
}
