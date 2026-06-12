<?php

namespace Tests\Feature\Pages;

use App\Pages\Components\Card;
use App\Pages\Components\DynamicCardRow;
use App\Pages\ExtensionRegistry;
use App\Plugins\ExtendPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExtendPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_extender_is_applied_to_a_page_render(): void
    {
        app(ExtensionRegistry::class)->setCurrentPlugin('demo-plugin');
        ExtendPage::make('server.poc')
            ->add(fn (array $models): Card => Card::make('redis-card')->title('Redis cache'))
            ->addRow(
                'details-card',
                fn (array $models): DynamicCardRow => DynamicCardRow::badge('details-card.cache', 'Cache')->value('on'),
                after: 'details-card.name',
            )
            ->register();
        app(ExtensionRegistry::class)->resetCurrentPlugin();

        $this->actingAs($this->user);

        $this->get(route('pages.server.poc', ['server' => $this->server]))
            ->assertSuccessful()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('schema.1.id', 'redis-card')
                ->where('schema.0.children.0.children.1.id', 'details-card.cache')
                ->etc()
            );
    }
}
