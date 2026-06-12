<?php

namespace Tests\Feature\Pages;

use App\Actions\Pages\RegisterPageRoutes;
use App\Enums\UserRole;
use App\Models\Server;
use App\Models\Worker;
use App\Pages\Areas\ServerArea;
use App\Pages\ExtensionActionRegistry;
use App\Plugins\RegisterExtensionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionActionTest extends TestCase
{
    use RefreshDatabase;

    private function registerNotifyAction(): void
    {
        app(ExtensionActionRegistry::class)->setCurrentPlugin('demo');
        RegisterExtensionAction::make('notify-worker')
            ->area(ServerArea::class)
            ->bind('worker', Worker::class, scopedTo: 'server')
            ->authorize(fn ($user, array $models): bool => $user->can('update', $models['server']))
            ->handler(fn (array $models, array $input): mixed => back()->with('success', 'notified'))
            ->register();
        app(ExtensionActionRegistry::class)->resetCurrentPlugin();

        app(RegisterPageRoutes::class)->handle();
    }

    public function test_extension_action_runs_for_authorized_writer(): void
    {
        $this->registerNotifyAction();
        $worker = Worker::factory()->create(['server_id' => $this->server->id]);

        $this->actingAs($this->user);

        $this->post(route('ext.demo.notify-worker', ['server' => $this->server]), ['worker' => $worker->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'notified');
    }

    public function test_extension_action_404s_for_worker_from_another_server(): void
    {
        $this->registerNotifyAction();
        $otherServer = Server::factory()->create(['project_id' => $this->server->project_id]);
        $foreignWorker = Worker::factory()->create(['server_id' => $otherServer->id]);

        $this->actingAs($this->user);

        $this->post(route('ext.demo.notify-worker', ['server' => $this->server]), ['worker' => $foreignWorker->id])
            ->assertNotFound();
    }

    public function test_extension_action_forbidden_for_read_only_role(): void
    {
        $this->registerNotifyAction();
        $worker = Worker::factory()->create(['server_id' => $this->server->id]);
        $this->server->project->users()->where('user_id', $this->user->id)->update(['role' => UserRole::USER]);

        $this->actingAs($this->user);

        $this->post(route('ext.demo.notify-worker', ['server' => $this->server]), ['worker' => $worker->id])
            ->assertForbidden();
    }
}
