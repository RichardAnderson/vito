<?php

namespace App\Http\Controllers;

use App\Actions\Component\CreateComponent;
use App\Actions\Component\SyncComponentVhost;
use App\Actions\Component\UpdateComponentBackends;
use App\Actions\Component\DeleteComponent;
use App\Actions\Component\MoveComponent;
use App\Actions\Component\UpdateComponent;
use App\Support\ComponentFunctions;
use App\Http\Resources\ServerResource;
use App\Models\Application;
use App\Models\Component;
use App\Models\Environment;
use App\Tables\Applications\ComponentTable;
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

#[Prefix('applications/{application}/environments/{environment}/components')]
#[Middleware(['auth', 'has-project'])]
class ComponentController extends Controller
{
    #[Get('/', name: 'environments.components')]
    public function index(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('viewAny', [Component::class, $application->project]);

        return Inertia::render('applications/environments/components', [
            'components' => ComponentTable::make($environment->components())->simplePaginate(),
            'servers' => ServerResource::collection($application->project->servers()->get()),
        ]);
    }

    #[Post('/', name: 'components.store')]
    public function store(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('create', [Component::class, $application->project]);

        app(CreateComponent::class)->create($environment, $request->all());

        return back()->with('info', 'Component is being installed.');
    }

    #[Get('/{component}', name: 'components.show')]
    public function show(Application $application, Environment $environment, Component $component): Response
    {
        abort_unless($environment->application_id === $application->id, 404);
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('view', $component);

        $component->load(['functions', 'server', 'backends.targetComponent']);

        return Inertia::render('applications/environments/component', [
            'scriptOverrides' => $environment->deploymentScripts()
                ->where('component_id', $component->id)
                ->get()
                ->map(fn ($script): array => [
                    'id' => $script->id,
                    'name' => $script->name,
                    'content' => (string) $script->content,
                ])
                ->values()
                ->all(),
            'backends' => $component->backends
                ->map(fn ($backend): array => [
                    'id' => $backend->id,
                    'target_component_id' => $backend->target_component_id,
                    'target' => $backend->targetComponent->name,
                    'port' => $backend->port,
                ])
                ->values()
                ->all(),
            'backendCandidates' => $environment->components()->with('functions')->get()
                ->filter(fn (Component $candidate): bool => $candidate->id !== $component->id && $candidate->hasFunction(ComponentFunctions::HTTP))
                ->map(fn (Component $candidate): array => ['id' => $candidate->id, 'name' => $candidate->name])
                ->values()
                ->all(),
        ]);
    }

    #[Get('/{component}/settings', name: 'components.settings')]
    public function settings(Application $application, Environment $environment, Component $component): Response
    {
        abort_unless($environment->application_id === $application->id, 404);
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('view', $component);

        $component->load(['functions', 'server']);

        return Inertia::render('applications/environments/component-settings');
    }

    #[Put('/{component}', name: 'components.update')]
    public function update(Request $request, Application $application, Environment $environment, Component $component): RedirectResponse
    {
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('update', $component);

        app(UpdateComponent::class)->update($component, $request->all());

        return back()->with('success', 'Component updated.');
    }

    #[Post('/{component}/move', name: 'components.move')]
    public function move(Request $request, Application $application, Environment $environment, Component $component): RedirectResponse
    {
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('update', $component);

        app(MoveComponent::class)->move($component, $request->all());

        return back()->with('info', 'Component is being moved.');
    }

    #[Post('/{component}/backends', name: 'components.backends')]
    public function backends(Request $request, Application $application, Environment $environment, Component $component): RedirectResponse
    {
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('update', $component);

        app(UpdateComponentBackends::class)->update($component, $request->all());

        app(SyncComponentVhost::class)->sync($component->fresh());

        return back()->with('success', 'Backends updated.');
    }

    #[Delete('/{component}', name: 'components.destroy')]
    public function destroy(Application $application, Environment $environment, Component $component): RedirectResponse
    {
        abort_unless($component->environment_id === $environment->id, 404);

        $this->authorize('delete', $component);

        app(DeleteComponent::class)->delete($component);

        return redirect()->route('environments.components', [$application->id, $environment->id])
            ->with('info', 'Component is being deleted.');
    }
}
