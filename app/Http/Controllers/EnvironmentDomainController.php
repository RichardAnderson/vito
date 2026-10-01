<?php

namespace App\Http\Controllers;

use App\Actions\EnvironmentDomain\CreateEnvironmentDomain;
use App\Actions\EnvironmentDomain\DeleteEnvironmentDomain;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentDomain;
use App\Enums\EnvironmentDomainType;
use App\Http\Resources\EnvironmentDomainResource;
use App\Tables\Applications\EnvironmentDomainTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('applications/{application}/environments/{environment}/domains')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentDomainController extends Controller
{
    #[Get('/', name: 'environments.domains')]
    public function index(Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('viewAny', [EnvironmentDomain::class, $application->project]);

        return Inertia::render('applications/environments/domains', [
            'domains' => EnvironmentDomainTable::make($environment->domains())->simplePaginate(),
            'aliases' => EnvironmentDomainResource::collection(
                $environment->domains()->where('type', EnvironmentDomainType::ALIAS)->get()
            ),
            'hasServingComponent' => $environment->servingComponent() !== null,
        ]);
    }

    #[Post('/', name: 'environments.domains.store')]
    public function store(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('create', [EnvironmentDomain::class, $application->project]);

        app(CreateEnvironmentDomain::class)->create($environment, $request->all());

        return back()->with('info', 'Domain is being created.');
    }

    #[Delete('/{environmentDomain}', name: 'environments.domains.destroy')]
    public function destroy(Application $application, Environment $environment, EnvironmentDomain $environmentDomain): RedirectResponse
    {
        abort_unless($environmentDomain->environment_id === $environment->id, 404);

        $this->authorize('delete', $environmentDomain);

        app(DeleteEnvironmentDomain::class)->delete($environmentDomain);

        return back()->with('info', 'Domain removed.');
    }
}
