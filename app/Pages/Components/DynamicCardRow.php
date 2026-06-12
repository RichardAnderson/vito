<?php

namespace App\Pages\Components;

use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A label/value row inside a Card. Display types: text, copyable, badge, link,
 * code, button. Value/color/href may be closures, evaluated per request.
 */
final class DynamicCardRow extends AbstractComponent
{
    private string $display = 'text';

    private ?string $label = null;

    private mixed $value = null;

    private string|Closure|null $color = null;

    private string|Closure|null $href = null;

    private ?DynamicButton $button = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'card-row';
    }

    public static function text(string $id, string $label): self
    {
        return self::make($id)->label($label)->as('text');
    }

    public static function copyable(string $id, string $label): self
    {
        return self::make($id)->label($label)->as('copyable');
    }

    public static function badge(string $id, string $label): self
    {
        return self::make($id)->label($label)->as('badge');
    }

    public static function link(string $id, string $label): self
    {
        return self::make($id)->label($label)->as('link');
    }

    public static function code(string $id, string $label): self
    {
        return self::make($id)->label($label)->as('code');
    }

    public static function button(string $id, string $label, DynamicButton $button): self
    {
        $row = self::make($id)->label($label)->as('button');
        $row->button = $button;

        return $row;
    }

    public function as(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function value(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function color(string|Closure $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function href(string|Closure $href): self
    {
        $this->href = $href;

        return $this;
    }

    /**
     * @return array<int, \App\Pages\PageAction>
     */
    public function actions(): array
    {
        return $this->button?->actions() ?? [];
    }

    /**
     * @return array<int, \App\Pages\DataEndpoint>
     */
    public function data(): array
    {
        return $this->button?->data() ?? [];
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'display' => $this->display,
            'label' => $this->label,
            'value' => $this->evaluate($this->value, $ctx),
            'color' => $this->evaluate($this->color, $ctx),
            'href' => $this->evaluate($this->href, $ctx),
            'button' => $this->button?->serialize($ctx),
        ];
    }
}
