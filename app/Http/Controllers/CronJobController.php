<?php

namespace App\Http\Controllers;

use App\Actions\CronJob\CreateCronJob;
use App\Actions\CronJob\DeleteCronJob;
use App\Actions\CronJob\DisableCronJob;
use App\Actions\CronJob\EditCronJob;
use App\Actions\CronJob\EnableCronJob;
use App\Actions\CronJob\SyncCronJobs;
use App\Exceptions\SSHError;
use App\Http\Resources\CronJobResource;
use App\Models\CronJob;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('servers/{server}')]
#[Middleware(['auth', 'has-project'])]
class CronJobController extends Controller
{
    #[Get('/cronjobs', name: 'cronjobs')]
    public function index(Server $server): Response
    {
        $this->authorize('viewAny', [CronJob::class, $server]);

        return Inertia::render('cronjobs/index', [
            'cronjobs' => CronJobResource::collection($server->cronJobs()->where('hidden', false)->latest()->simplePaginate(config('web.pagination_size'))),
            'sites' => $server->sites()->select('id', 'domain')->get(),
        ]);
    }

    /**
     * @throws SSHError
     */
    #[Post('/cronjobs', name: 'cronjobs.store')]
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('create', [CronJob::class, $server]);

        app(CreateCronJob::class)->create($server, $request->all());

        return back()
            ->with('success', 'Cron job has been created.');
    }

    /**
     * @throws SSHError
     */
    #[Put('/cronjobs/{cronJob}', name: 'cronjobs.update')]
    public function update(Request $request, Server $server, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('update', [$cronJob, $server]);

        app(EditCronJob::class)->edit($server, $cronJob, $request->all());

        return back()
            ->with('success', 'Cron job has been updated.');
    }

    /**
     * @throws SSHError
     */
    #[Post('/cronjobs/{cronJob}/enable', name: 'cronjobs.enable')]
    public function enable(Server $server, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('update', [$cronJob, $server]);

        app(EnableCronJob::class)->enable($server, $cronJob);

        return back()
            ->with('success', 'Cron job has been enabled.');
    }

    /**
     * @throws SSHError
     */
    #[Post('/cronjobs/{cronJob}/disable', name: 'cronjobs.disable')]
    public function disable(Server $server, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('update', [$cronJob, $server]);

        app(DisableCronJob::class)->disable($server, $cronJob);

        return back()
            ->with('success', 'Cron job has been disabled.');
    }

    /**
     * @throws SSHError
     */
    #[Delete('/cronjobs/{cronJob}', name: 'cronjobs.destroy')]
    public function destroy(Server $server, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('delete', [$cronJob, $server]);

        app(DeleteCronJob::class)->delete($server, $cronJob);

        return back()
            ->with('success', 'Cron job has been deleted.');
    }

    /**
     * @throws SSHError
     */
    #[Post('/cronjobs/sync', name: 'cronjobs.sync')]
    public function sync(Server $server): RedirectResponse
    {
        $this->authorize('create', [CronJob::class, $server]);

        app(SyncCronJobs::class)->sync($server);

        return back()
            ->with('success', 'Cron jobs synced successfully.');
    }
}
