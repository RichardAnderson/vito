<?php

namespace App\Http\Controllers;

use App\Actions\Site\UpdateDeploymentScript;
use App\Models\Application;
use App\Models\Component;
use App\Models\DeploymentScript;
use App\Models\Environment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('applications/{application}/environments/{environment}/scripts')]
#[Middleware(['auth', 'has-project'])]
class EnvironmentDeploymentScriptController extends Controller
{
    #[Put('/{script}', name: 'environments.scripts.update')]
    public function update(Request $request, Application $application, Environment $environment, DeploymentScript $script): RedirectResponse
    {
        abort_unless($script->environment_id === $environment->id, 404);

        $this->authorize('update', $environment);

        app(UpdateDeploymentScript::class)->update($script, $request->all());

        return back()->with('success', 'Script saved.');
    }

    #[Post('/', name: 'environments.scripts.store')]
    public function store(Request $request, Application $application, Environment $environment): RedirectResponse
    {
        abort_unless($environment->application_id === $application->id, 404);

        $this->authorize('update', $environment);

        $input = $request->validate([
            'component_id' => ['required', Rule::exists('components', 'id')->where('environment_id', $environment->id)],
            'name' => ['required', Rule::in(['build', 'pre-flight'])],
        ]);

        /** @var Component $component */
        $component = $environment->components()->findOrFail($input['component_id']);

        $this->authorize('update', $component);

        $environment->deploymentScripts()->firstOrCreate([
            'component_id' => $component->id,
            'name' => $input['name'],
        ], [
            'content' => '',
        ]);

        return back()->with('success', 'Override created.');
    }

    #[Delete('/{script}', name: 'environments.scripts.destroy')]
    public function destroy(Application $application, Environment $environment, DeploymentScript $script): RedirectResponse
    {
        abort_unless($script->environment_id === $environment->id, 404);
        abort_unless($script->component_id !== null, 404);

        $this->authorize('update', $environment);

        $script->delete();

        return back()->with('success', 'Override removed.');
    }
}
