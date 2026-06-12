<?php

namespace Tests\Unit\Pages;

use App\Pages\Components\Card;
use App\Pages\Components\DynamicCardRow;
use App\Pages\ExtensionRegistry;
use App\Pages\Schema\EvaluationContext;
use RuntimeException;
use Tests\TestCase;

class ExtensionRegistryTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseSchema(): array
    {
        return [
            [
                'id' => 'details-card',
                'type' => 'card',
                'children' => [
                    ['id' => 'details-card.name', 'type' => 'card-row'],
                    ['id' => 'details-card.ip', 'type' => 'card-row'],
                ],
            ],
        ];
    }

    public function test_applies_add_replace_and_insert_ops(): void
    {
        $registry = new ExtensionRegistry;
        $registry->setCurrentPlugin('p1');
        $registry->add('page', [
            ['type' => 'add-root', 'factory' => fn (): Card => Card::make('extra-card')->title('Extra'), 'after' => null, 'before' => null],
            ['type' => 'add-child', 'target' => 'details-card', 'factory' => fn (): DynamicCardRow => DynamicCardRow::text('details-card.region', 'Region')->value('eu'), 'after' => 'details-card.name', 'before' => null],
            ['type' => 'replace', 'target' => 'details-card.ip', 'factory' => fn (): DynamicCardRow => DynamicCardRow::badge('details-card.ip', 'IP')->value('1.1.1.1'), 'after' => null, 'before' => null],
        ]);

        $result = $registry->apply('page', $this->baseSchema(), EvaluationContext::make());

        $this->assertSame('extra-card', $result[1]['id']);

        $rows = $result[0]['children'];
        $this->assertSame(['details-card.name', 'details-card.region', 'details-card.ip'], array_column($rows, 'id'));
        $this->assertSame('badge', $rows[2]['display']);
    }

    public function test_drops_whole_plugin_contribution_when_an_op_throws(): void
    {
        $registry = new ExtensionRegistry;
        $registry->setCurrentPlugin('faulty');
        $registry->add('page', [
            ['type' => 'add-child', 'target' => 'details-card', 'factory' => fn (): DynamicCardRow => DynamicCardRow::text('details-card.ok', 'Ok')->value('x'), 'after' => null, 'before' => null],
            ['type' => 'add-root', 'factory' => fn () => throw new RuntimeException('boom'), 'after' => null, 'before' => null],
        ]);

        $result = $registry->apply('page', $this->baseSchema(), EvaluationContext::make());

        // The first op's row must NOT survive — the whole contribution is atomic.
        $this->assertSame(['details-card.name', 'details-card.ip'], array_column($result[0]['children'], 'id'));
        $this->assertCount(1, $result);
    }

    public function test_address_collision_drops_contribution(): void
    {
        $registry = new ExtensionRegistry;
        $registry->setCurrentPlugin('colliding');
        $registry->add('page', [
            ['type' => 'add-root', 'factory' => fn (): Card => Card::make('details-card')->title('Dup'), 'after' => null, 'before' => null],
        ]);

        $result = $registry->apply('page', $this->baseSchema(), EvaluationContext::make());

        $this->assertCount(1, $result);
        $this->assertSame('details-card', $result[0]['id']);
    }

    public function test_independent_plugins_are_isolated(): void
    {
        $registry = new ExtensionRegistry;
        $registry->setCurrentPlugin('good');
        $registry->add('page', [
            ['type' => 'add-root', 'factory' => fn (): Card => Card::make('good-card'), 'after' => null, 'before' => null],
        ]);
        $registry->setCurrentPlugin('bad');
        $registry->add('page', [
            ['type' => 'add-root', 'factory' => fn () => throw new RuntimeException('boom'), 'after' => null, 'before' => null],
        ]);

        $result = $registry->apply('page', $this->baseSchema(), EvaluationContext::make());

        $ids = array_column($result, 'id');
        $this->assertContains('good-card', $ids);
        $this->assertCount(2, $result);
    }
}
