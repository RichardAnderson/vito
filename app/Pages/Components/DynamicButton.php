<?php

namespace App\Pages\Components;

use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A button that either dispatches a named page action or opens a dialog. Never
 * carries a raw URL — the framework builds URLs from action ids so authorization
 * metadata stays attached. The action may be referenced by id (string/closure) or
 * by attaching a PageAction object, which is then harvested into the page.
 */
final class DynamicButton extends AbstractComponent
{
    private string|Closure|null $label = null;

    private string|Closure $variant = 'default';

    private string|Closure|null $icon = null;

    private string|Closure|null $action = null;

    private ?PageAction $actionObject = null;

    private ?DynamicDialog $dialog = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'button';
    }

    public function label(string|Closure|null $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function variant(string|Closure $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function icon(string|Closure $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function action(string|Closure|PageAction $action): self
    {
        if ($action instanceof PageAction) {
            $this->actionObject = $action;
            $this->action = $action->id();

            return $this;
        }

        $this->action = $action;

        return $this;
    }

    public function dialog(DynamicDialog $dialog): self
    {
        $this->dialog = $dialog;

        return $this;
    }

    /**
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return array_merge(
            $this->actionObject !== null ? [$this->actionObject] : [],
            $this->dialog?->actions() ?? [],
        );
    }

    /**
     * @return array<int, \App\Pages\DataEndpoint>
     */
    public function data(): array
    {
        return $this->dialog?->data() ?? [];
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'label' => $this->evaluate($this->label, $ctx),
            'variant' => $this->evaluate($this->variant, $ctx),
            'icon' => $this->evaluate($this->icon, $ctx),
            'action' => $this->evaluate($this->action, $ctx),
            'dialog' => $this->dialog?->serialize($ctx),
        ];
    }
}
