<?php

namespace Tests\Feature\Pages;

use App\Pages\Components\Card;
use App\Pages\ExtensionRegistry;
use App\Plugins\ExtendPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PageSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_contributions_are_attached_to_a_hard_coded_page(): void
    {
        app(ExtensionRegistry::class)->setCurrentPlugin('demo');
        ExtendPage::make('workers')
            ->add(
                fn (array $models): Card => Card::make('audit-card')->title('Audit log'),
                after: 'workers.before-table',
            )
            ->register();
        app(ExtensionRegistry::class)->resetCurrentPlugin();

        $this->actingAs($this->user);

        $response = $this->get(route('workers', ['server' => $this->server]));
        $response->assertSuccessful();

        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('slots', $props);
        $this->assertSame('audit-card', $props['slots']['workers.before-table'][0]['id']);
    }

    public function test_no_slots_prop_overhead_without_extenders(): void
    {
        $this->actingAs($this->user);

        $this->get(route('workers', ['server' => $this->server]))
            ->assertSuccessful()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('slots')->etc());
    }
}
