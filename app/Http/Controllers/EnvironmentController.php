<?php

namespace App\Http\Controllers;

use App\Actions\Environment\BuildEnvironmentTopology;
use App\Actions\Environment\CheckEnvironmentNetworks;
use App\Actions\Environment\ListEnvironmentResourceOptions;
use App\Actions\Environment\CreateEnvironment;
use App\Actions\Environment\DeleteEnvironment;
use App\Actions\Environment\UpdateEnvironment;
use App\Enums\EnvironmentDomainType;
use App\Http\Resources\EnvironmentDomainResource;
use App\Http\Resources\ServerResource;
use App\Models\Application;
use App\Models\Environment;
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

#[Prefix('applications/{application}/environments')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentController extends Controller
{
    #[Post('/', name: 'environments.store')]
    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('create', [Environment::class, $application->project]);

        $environment = app(CreateEnvironment::class)->create($application, $request->all());

        return redirect()->route('environments.show', [$application->id, $environment->id])
            ->with('success', 'Environment created.');
    }

    #[Get('/{environment}', name: 'environments.show')]
    public function show(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('view', $environment);

        $environment->loadCount(['components', 'domains']);

        return Inertia::render('applications/environments/show', [
            'strandedServers' => app(CheckEnvironmentNetworks::class)->unreachableServers($environment),
            'topology' => app(BuildEnvironmentTopology::class)->build($environment),
            'aliases' => EnvironmentDomainResource::collection(
                $environment->domains()->where('type', EnvironmentDomainType::ALIAS)->get()
            ),
            ...app(ListEnvironmentResourceOptions::class)->options($application),
            'projectServers' => ServerResource::collection($application->project->servers()->get()),
        ]);
    }

    #[Get('/{environment}/settings', name: 'environments.settings')]
    public function settings(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('view', $environment);

        return Inertia::render('applications/environments/settings', [
            'scripts' => $environment->deploymentScripts()->whereNull('component_id')->get()
                ->map(fn ($script): array => [
                    'id' => $script->id,
                    'name' => $script->name,
                    'content' => (string) $script->content,
                ])
                ->values()
                ->all(),
        ]);
    }

    #[Delete('/{environment}', name: 'environments.destroy')]
    public function destroy(Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('delete', $environment);

        app(DeleteEnvironment::class)->delete($environment);

        return redirect()->route('applications.show', $application->id)
            ->with('info', 'Environment is being deleted.');
    }

    #[Put('/{environment}', name: 'environments.update')]
    public function update(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        app(UpdateEnvironment::class)->update($environment, $request->all());

        return back()->with('success', 'Environment updated.');
    }
}
