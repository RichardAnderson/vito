<?php

namespace App\Http\Controllers;

use App\Actions\Environment\AttachEnvironmentResource;
use App\Actions\Environment\DetachEnvironmentResource;
use App\Actions\Environment\ListEnvironmentResourceOptions;
use App\Actions\Environment\ProvisionEnvironmentDatabase;
use App\Models\Application;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\Environment;
use App\Models\EnvironmentResource;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('applications/{application}/environments/{environment}/resources')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentResourceController extends Controller
{
    #[Get('/', name: 'environments.resources')]
    public function index(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('view', $environment);

        $attached = $environment->resources()->with('resource.server')->get()
            ->map(fn (EnvironmentResource $row): array => [
                'id' => $row->id,
                'type' => $this->resourceKind($row),
                'name' => $this->resourceLabel($row),
                'server' => $this->resourceServer($row),
            ])
            ->all();

        return Inertia::render('applications/environments/resources', [
            'attached' => $attached,
            ...app(ListEnvironmentResourceOptions::class)->options($application),
        ]);
    }

    private function resourceLabel(EnvironmentResource $row): string
    {
        $resource = $row->resource;

        return match (true) {
            $resource instanceof Database => $resource->name,
            $resource instanceof DatabaseUser => $resource->username,
            $resource instanceof Service => $resource->name.' '.$resource->version,
            default => '#'.$row->resource_id,
        };
    }

    private function resourceServer(EnvironmentResource $row): ?string
    {
        $resource = $row->resource;

        if ($resource instanceof Database || $resource instanceof DatabaseUser || $resource instanceof Service) {
            return $resource->server->name;
        }

        return null;
    }

    private function resourceKind(EnvironmentResource $row): string
    {
        $resource = $row->resource;

        return match (true) {
            $resource instanceof Database => 'database',
            $resource instanceof DatabaseUser => 'database user',
            $resource instanceof Service => $resource->name,
            default => class_basename($row->resource_type),
        };
    }

    #[Post('/', name: 'environments.resources.store')]
    public function store(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        app(AttachEnvironmentResource::class)->attach($environment, $request->all());

        return back()->with('success', 'Resource attached.');
    }

    #[Post('/databases', name: 'environments.resources.databases')]
    public function database(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        $result = app(ProvisionEnvironmentDatabase::class)->provision($environment, $request->all());

        return back()->with('success', 'Database attached.')->with('data', [
            'alias' => $result['alias'],
            'password' => $result['password'],
            'credentials_pending' => $result['credentials_pending'],
        ]);
    }

    #[Delete('/{resource}', name: 'environments.resources.destroy')]
    public function destroy(Application $application, Environment $environment, EnvironmentResource $resource): RedirectResponse
    {
        abort_unless($resource->environment_id === $environment->id, 404);

        $this->authorize('update', $environment);

        app(DetachEnvironmentResource::class)->detach($resource);

        return back()->with('success', 'Resource detached. The resource itself is untouched.');
    }
}
