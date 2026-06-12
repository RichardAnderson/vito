<?php

namespace App\Pages\Components;

use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A banner/alert (info/warning/destructive) with an optional action button.
 */
final class DynamicAlert extends AbstractComponent
{
    private string|Closure $variant = 'info';

    private string|Closure|null $title = null;

    private string|Closure|null $message = null;

    private ?DynamicButton $button = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'alert';
    }

    public function variant(string|Closure $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function title(string|Closure $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function message(string|Closure $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function button(DynamicButton $button): self
    {
        $this->button = $button;

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
            'variant' => $this->evaluate($this->variant, $ctx),
            'title' => $this->evaluate($this->title, $ctx),
            'message' => $this->evaluate($this->message, $ctx),
            'button' => $this->button?->serialize($ctx),
        ];
    }
}
