<?php

namespace App\Http\Controllers;

use App\Actions\Environment\DeleteEnvironmentVariable;
use App\Actions\Environment\ResolveComponentVariables;
use App\Actions\Environment\UpsertEnvironmentVariable;
use App\Http\Resources\ComponentResource;
use App\Models\Application;
use App\Models\Component;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('applications/{application}/environments/{environment}/variables')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentVariableController extends Controller
{
    private const MASK = '••••••••';

    #[Get('/', name: 'environments.variables')]
    public function index(Request $request, Application $application, Environment $environment): Response
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('view', $environment);

        $componentId = $request->integer('component') ?: null;
        $component = $componentId !== null
            ? $environment->components()->whereKey($componentId)->first()
            : null;

        return Inertia::render('applications/environments/variables', [
            'variables' => $this->payload($environment, $component),
            'scopeComponentId' => $component?->id,
            'components' => ComponentResource::collection($environment->components()->get()),
        ]);
    }

    #[Post('/', name: 'environments.variables.store')]
    public function store(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        $existing = $request->integer('id')
            ? $environment->variables()->whereKey($request->integer('id'))->first()
            : null;

        app(UpsertEnvironmentVariable::class)->upsert($environment, $request->all(), $existing);

        return back()->with('success', 'Variable saved.');
    }

    #[Delete('/{variable}', name: 'environments.variables.destroy')]
    public function destroy(Application $application, Environment $environment, EnvironmentVariable $variable): RedirectResponse
    {
        abort_unless($variable->environment_id === $environment->id, 404);

        $this->authorize('update', $environment);

        app(DeleteEnvironmentVariable::class)->delete($variable);

        return back()->with('success', 'Variable removed.');
    }

    /**
     * A secret's value is replaced server-side and never reaches the Inertia payload.
     *
     * @return array<int, array{id: ?int, key: string, value: ?string, is_secret: bool, source: string}>
     */
    private function payload(Environment $environment, ?Component $component): array
    {
        if ($component !== null) {
            $rows = [];

            foreach (app(ResolveComponentVariables::class)->resolve($component) as $key => $entry) {
                $override = $environment->variables()
                    ->where('component_id', $component->id)
                    ->where('key', $key)
                    ->first();

                $rows[] = [
                    'id' => $override?->id,
                    'key' => $key,
                    'value' => $entry['is_secret'] ? self::MASK : $entry['value'],
                    'is_secret' => $entry['is_secret'],
                    'source' => $entry['source'],
                ];
            }

            return $rows;
        }

        return $environment->variables()
            ->whereNull('component_id')
            ->orderBy('key')
            ->get()
            ->map(fn (EnvironmentVariable $variable): array => [
                'id' => $variable->id,
                'key' => $variable->key,
                'value' => $variable->is_secret ? self::MASK : $variable->value,
                'is_secret' => $variable->is_secret,
                'source' => 'environment',
            ])
            ->all();
    }
}
