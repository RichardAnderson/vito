<?php

namespace Tests\Unit\Pages;

use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\ServerArea;
use App\Pages\Components\Card;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicCardRow;
use App\Pages\Components\DynamicCodeEditor;
use App\Pages\Components\DynamicDialog;
use App\Pages\Components\Entry;
use App\Pages\Components\Forms\Select;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Pages\PageComponentException;
use App\Pages\Schema\EvaluationContext;
use RuntimeException;
use Tests\TestCase;

class PageHarvestTest extends TestCase
{
    public function test_editable_entry_serializes_to_the_card_row_button_shape(): void
    {
        $entry = Entry::make('details-card.php-version')
            ->label('PHP version')
            ->state('8.4')
            ->action(PageAction::make('update-php-version')->patch()
                ->modalHeading('Change PHP version')
                ->form([Select::make('version')->label('Version')->options(['8.4' => '8.4'])])
                ->run(fn () => null));

        $wire = $entry->serialize(EvaluationContext::make());

        $this->assertSame('card-row', $wire['type']);
        $this->assertSame('button', $wire['display']);
        $this->assertSame('PHP version', $wire['label']);
        $this->assertNull($wire['value']);

        $button = $wire['button'];
        $this->assertSame('details-card.php-version.button', $button['id']);
        $this->assertSame('8.4', $button['label']);
        $this->assertSame('outline', $button['variant']);
        $this->assertNull($button['action']);

        $dialog = $button['dialog'];
        $this->assertSame('php-version-dialog', $dialog['id']);
        $this->assertSame('Change PHP version', $dialog['title']);
        $this->assertSame('update-php-version', $dialog['action']);
        $this->assertNotNull($dialog['form']);
        $this->assertSame('version', $dialog['form'][0]['name']);
        $this->assertSame('select', $dialog['form'][0]['type']);

        $this->assertSame(['update-php-version'], array_map(fn (PageAction $a): string => $a->id(), $entry->actions()));
    }

    public function test_read_only_entry_uses_its_display_and_state(): void
    {
        $entry = Entry::make('domain')->label('Domain')
            ->link('https://example.test')
            ->state('example.test');

        $wire = $entry->serialize(EvaluationContext::make());

        $this->assertSame('link', $wire['display']);
        $this->assertSame('example.test', $wire['value']);
        $this->assertSame('https://example.test', $wire['href']);
        $this->assertNull($wire['button']);
        $this->assertSame([], $entry->actions());
    }

    public function test_hidden_entry_serializes_to_null(): void
    {
        $entry = Entry::make('x')->label('X')->visible(false)
            ->action(PageAction::make('x-action')->run(fn () => null));

        $this->assertNull($entry->serialize(EvaluationContext::make()));
    }

    public function test_card_composes_child_addresses_from_keys(): void
    {
        $card = Card::make('details-card')->schema([
            Entry::make('php-version')->label('PHP')->action(PageAction::make('update-php')->run(fn () => null)),
        ]);

        $wire = $card->serialize(EvaluationContext::make());

        $this->assertSame('details-card.php-version', $wire['children'][0]['id']);
        $this->assertSame('details-card.php-version.button', $wire['children'][0]['button']['id']);
        $this->assertSame('php-version-dialog', $wire['children'][0]['button']['dialog']['id']);
    }

    public function test_page_harvests_attached_and_headless_behaviour(): void
    {
        $page = $this->page();

        $actionIds = array_map(fn (PageAction $a): string => $a->id(), $page->allActions());
        $dataIds = array_map(fn (DataEndpoint $d): string => $d->id(), $page->allData());

        $this->assertEqualsCanonicalizing(['update-php-version', 'save-vhost', 'headless-action'], $actionIds);
        $this->assertEqualsCanonicalizing(['load-vhost', 'headless-data'], $dataIds);
    }

    public function test_schema_construction_does_not_evaluate_closures(): void
    {
        $page = new class extends AbstractPage
        {
            public static function id(): string
            {
                return 'defer';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'defer';
            }

            public function schema(): array
            {
                return [
                    Card::make('card')->schema([
                        Entry::make('boom')
                            ->label('Boom')
                            ->state(fn () => throw new RuntimeException('evaluated at build time!'))
                            ->visible(fn () => throw new RuntimeException('visible evaluated at build time!'))
                            ->action(PageAction::make('boom-action')->run(fn () => null)),
                    ]),
                ];
            }
        };

        $ids = array_map(fn (PageAction $a): string => $a->id(), $page->allActions());

        $this->assertSame(['boom-action'], $ids);
    }

    public function test_duplicate_action_id_across_attached_and_headless_throws(): void
    {
        $page = new class extends AbstractPage
        {
            public static function id(): string
            {
                return 'dup';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'dup';
            }

            public function schema(): array
            {
                return [
                    Entry::make('row')->label('Row')
                        ->action(PageAction::make('clash')->run(fn () => null)),
                ];
            }

            public function headless(): array
            {
                return [PageAction::make('clash')->authorize(fn () => true)->run(fn () => null)];
            }
        };

        $this->expectException(PageComponentException::class);

        $page->allActions();
    }

    private function page(): AbstractPage
    {
        return new class extends AbstractPage
        {
            public static function id(): string
            {
                return 'harvest';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'harvest';
            }

            public function schema(): array
            {
                $editor = DynamicCodeEditor::make('vhost-editor')
                    ->load(DataEndpoint::make('load-vhost')->public()->resolve(fn () => []))
                    ->save(PageAction::make('save-vhost')->put()->authorize(fn () => true)->run(fn () => null));

                return [
                    Card::make('card')->schema([
                        Entry::make('php')->label('PHP')
                            ->action(PageAction::make('update-php-version')->patch()->authorize(fn () => true)->run(fn () => null)),
                        DynamicCardRow::button('card.vhost', 'VHost', DynamicButton::make('card.vhost.button')
                            ->label('Edit')
                            ->dialog(DynamicDialog::make('vhost-dialog')->sheet()->editor($editor))),
                    ]),
                ];
            }

            public function headless(): array
            {
                return [
                    PageAction::make('headless-action')->authorize(fn () => true)->run(fn () => null),
                    DataEndpoint::make('headless-data')->public()->resolve(fn () => []),
                ];
            }
        };
    }
}
