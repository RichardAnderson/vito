<?php

namespace App\Http\Controllers;

use App\Actions\Application\CreateApplication;
use App\Actions\Application\DeleteApplication;
use App\Actions\Application\UpdateApplication;
use App\Http\Resources\EnvironmentResource;
use App\Http\Resources\SourceControlResource;
use App\Models\Application;
use App\Tables\ApplicationTable;
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

#[Prefix('applications')]
#[Middleware(['auth', 'has-project'])]
class ApplicationsController extends Controller
{
    #[Get('/', name: 'applications')]
    public function index(): Response
    {
        $project = user()->currentProject;

        $this->authorize('viewAny', [Application::class, $project]);

        return Inertia::render('applications/index', [
            'applications' => ApplicationTable::make($project->applications())->simplePaginate(),
            'sourceControls' => SourceControlResource::collection(user()->sourceControls()->get()),
        ]);
    }

    #[Post('/', name: 'applications.store')]
    public function store(Request $request): RedirectResponse
    {
        $project = user()->currentProject;

        $this->authorize('create', [Application::class, $project]);

        $application = app(CreateApplication::class)->create($project, $request->all());

        return redirect()->route('applications.show', $application->id)
            ->with('success', 'Application created.');
    }

    #[Get('/{application}', name: 'applications.show')]
    public function show(Application $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['environments' => fn ($query) => $query->withCount(['components', 'domains'])->with('domains')]);

        return Inertia::render('applications/show', [
            'environments' => EnvironmentResource::collection($application->environments),
        ]);
    }

    #[Get('/{application}/settings', name: 'applications.settings')]
    public function settings(Application $application): Response
    {
        $this->authorize('view', $application);

        return Inertia::render('applications/settings');
    }

    #[Delete('/{application}', name: 'applications.destroy')]
    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        app(DeleteApplication::class)->delete($application);

        return redirect()->route('applications')->with('info', 'Application is being deleted.');
    }

    #[Put('/{application}', name: 'applications.update')]
    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        app(UpdateApplication::class)->update($application, $request->all());

        return back()->with('success', 'Application updated.');
    }
}
