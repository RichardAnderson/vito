<?php

namespace Tests\Unit\Pages;

use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\ServerArea;
use App\Pages\Components\Control;
use App\Pages\Components\Forms\Control as FieldControl;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Tests\TestCase;

class ControlsTest extends TestCase
{
    public function test_field_control_resolves_to_a_component_field(): void
    {
        $field = FieldControl::make('ssl')
            ->using('ssl-matcher')
            ->componentProps(['original' => 'example.test'])
            ->resolve(EvaluationContext::make())
            ->toArray();

        $this->assertSame('component', $field['type']);
        $this->assertSame('ssl', $field['name']);
        $this->assertSame('ssl-matcher', $field['component']);
        $this->assertSame(['original' => 'example.test'], $field['componentProps']);
    }

    public function test_field_control_props_are_closure_evaluated(): void
    {
        $field = FieldControl::make('ssl')
            ->using('ssl-matcher')
            ->componentProps(fn (): array => ['computed' => true])
            ->resolve(EvaluationContext::make())
            ->toArray();

        $this->assertSame(['computed' => true], $field['componentProps']);
    }

    public function test_panel_control_serializes_using_and_props(): void
    {
        $wire = Control::make('cert-panel')
            ->using('certificate-panel')
            ->with(['ssl_id' => 7])
            ->serialize(EvaluationContext::make());

        $this->assertSame('control', $wire['type']);
        $this->assertSame('cert-panel', $wire['id']);
        $this->assertSame('certificate-panel', $wire['using']);
        $this->assertSame(['ssl_id' => 7], $wire['props']);
    }

    public function test_panel_control_endpoints_are_harvested(): void
    {
        $page = new class extends AbstractPage
        {
            public static function id(): string
            {
                return 'control-fixture';
            }

            public function area(): AbstractArea
            {
                return app(ServerArea::class);
            }

            public function slug(): string
            {
                return 'control-fixture';
            }

            public function schema(): array
            {
                return [
                    Control::make('panel')->using('p')
                        ->withActions([PageAction::make('panel-action')->authorize(fn () => true)->run(fn () => null)])
                        ->withData([DataEndpoint::make('panel-data')->public()->resolve(fn (): array => [])]),
                ];
            }
        };

        $this->assertContains('panel-action', array_map(fn (PageAction $a): string => $a->id(), $page->allActions()));
        $this->assertContains('panel-data', array_map(fn (DataEndpoint $d): string => $d->id(), $page->allData()));
    }
}
