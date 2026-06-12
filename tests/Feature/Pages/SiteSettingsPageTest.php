<?php

namespace Tests\Feature\Pages;

use App\Enums\ServiceStatus;
use App\Enums\UserRole;
use App\Facades\SSH;
use App\Models\Service;
use App\Pages\Schema\EvaluationContext;
use App\Pages\SiteSettings\SiteSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cards(): Collection
    {
        $models = ['site' => $this->site, 'server' => $this->server];
        $ctx = EvaluationContext::make($models, $this->user);

        $schema = array_map(
            fn ($node): array => $node->serialize($ctx),
            (new SiteSettingsPage)->schema(),
        );

        return collect($schema[0]['children']);
    }

    public function test_renders_the_settings_archetype_schema(): void
    {
        $cards = $this->cards();
        $this->assertEqualsCanonicalizing(['details-card', 'delete-card'], $cards->pluck('id')->all());

        $rows = collect($cards->firstWhere('id', 'details-card')['children']);
        $php = $rows->firstWhere('id', 'details-card.php-version');
        $this->assertSame('button', $php['display']);
        $this->assertSame('update-php-version', $php['button']['dialog']['action']);

        // The VHost template editor (the headline conversion) is a sheet-backed code editor.
        $vhostTemplate = $rows->firstWhere('id', 'details-card.vhost-template');
        $editor = $vhostTemplate['button']['dialog'];
        $this->assertTrue($editor['sheet']);
        $this->assertSame('vhost-template', $editor['editor']['load']);
        $this->assertSame('update-vhost-template', $editor['editor']['save']);
        $this->assertSame('vhost-preview', $editor['editor']['preview']);

        $delete = collect($cards->firstWhere('id', 'delete-card')['children'])->firstWhere('id', 'delete-card.delete');
        $this->assertSame($this->site->domain, $delete['button']['dialog']['confirmText']);
        $this->assertSame('destroy', $delete['button']['dialog']['action']);
    }

    public function test_update_php_action_delegates_to_the_action_class(): void
    {
        SSH::fake();
        Service::query()->create([
            'server_id' => $this->server->id,
            'type' => 'php',
            'name' => 'php',
            'version' => '8.4',
            'status' => ServiceStatus::READY,
        ]);

        $action = $this->action('update-php-version');
        $models = ['site' => $this->site, 'server' => $this->server];

        $this->assertTrue($action->isAuthorized($this->user, $models));
        $action->dispatch($models, ['version' => '8.4'], Request::create('/'));

        $this->assertSame('8.4', $this->site->refresh()->php_version);
    }

    public function test_actions_require_write_access(): void
    {
        $this->server->project->users()->where('user_id', $this->user->id)->update(['role' => UserRole::USER]);
        $this->user->refresh();

        $models = ['site' => $this->site, 'server' => $this->server];

        $this->assertFalse($this->action('update-php-version')->isAuthorized($this->user, $models));
        $this->assertFalse($this->action('destroy')->isAuthorized($this->user, $models));
    }

    public function test_co_located_and_headless_behaviour_is_harvested(): void
    {
        $actionIds = array_map(fn ($action): string => $action->id(), (new SiteSettingsPage)->allActions());
        $dataIds = array_map(fn ($endpoint): string => $endpoint->id(), (new SiteSettingsPage)->allData());

        // update-vhost-template is co-located on the VhostEditor's code editor (harvested
        // from the schema tree); update-port is headless (no row of its own).
        $this->assertContains('update-vhost-template', $actionIds);
        $this->assertContains('update-port', $actionIds);
        $this->assertContains('vhost-template', $dataIds);
        $this->assertContains('worker-env', $dataIds);

        // The page no longer maintains a parallel actions() array.
        $this->assertSame([], (new SiteSettingsPage)->actions());
    }

    private function action(string $id): \App\Pages\PageAction
    {
        /** @var Collection<int, \App\Pages\PageAction> $actions */
        $actions = collect((new SiteSettingsPage)->allActions());

        return $actions->firstWhere(fn ($action): bool => $action->id() === $id);
    }
}
