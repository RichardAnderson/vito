<?php

namespace App\Http\Controllers;

use App\Actions\Environment\DeployEnvironment;
use App\Http\Resources\EnvironmentDeploymentResource;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentDeployment;
use App\Tables\Applications\EnvironmentDeploymentTable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('applications/{application}/environments/{environment}/deployments')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentDeploymentController extends Controller
{
    #[Get('/', name: 'environments.deployments')]
    public function index(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('view', $environment);

        return Inertia::render('applications/environments/deployments', [
            'deployments' => EnvironmentDeploymentTable::make($environment->environmentDeployments())->simplePaginate(),
        ]);
    }

    #[Post('/', name: 'environments.deploy')]
    public function deploy(Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        $run = app(DeployEnvironment::class)->run($environment);

        return redirect()->route('environments.deployments.show', [$application->id, $environment->id, $run->id])
            ->with('info', 'Deployment started.');
    }

    #[Get('/{run}', name: 'environments.deployments.show')]
    public function show(Application $application, Environment $environment, EnvironmentDeployment $run): Response
    {
        abort_unless($run->environment_id === $environment->id, 404);

        $this->authorize('view', $environment);

        $run->load(['deployments.component']);

        return Inertia::render('applications/environments/deployment-run', [
            'run' => EnvironmentDeploymentResource::make($run),
        ]);
    }
}
