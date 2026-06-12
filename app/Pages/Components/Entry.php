<?php

namespace App\Pages\Components;

use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A settings row (Filament-style infolist entry). Read-only by default — pick a
 * display with text()/copyable()/badge()/link()/code() and feed it ->state(). Attach
 * ->action(PageAction) to make it editable: it renders as a button whose label is the
 * current state, opening the action's modal (heading/form/confirm all live on the
 * action). Serializes to the frozen `card-row` wire shape.
 */
final class Entry extends AbstractComponent
{
    private string $display = 'text';

    private string|Closure|null $label = null;

    private mixed $state = null;

    private string|Closure|null $color = null;

    private string|Closure|null $url = null;

    private ?PageAction $action = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'card-row';
    }

    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function state(mixed $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function text(): self
    {
        $this->display = 'text';

        return $this;
    }

    public function copyable(): self
    {
        $this->display = 'copyable';

        return $this;
    }

    public function badge(): self
    {
        $this->display = 'badge';

        return $this;
    }

    public function code(): self
    {
        $this->display = 'code';

        return $this;
    }

    public function link(string|Closure|null $url = null): self
    {
        $this->display = 'link';
        if ($url !== null) {
            $this->url = $url;
        }

        return $this;
    }

    public function color(string|Closure $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function url(string|Closure $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function action(PageAction $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return $this->action !== null ? [$this->action] : [];
    }

    protected function props(EvaluationContext $ctx): array
    {
        if ($this->action !== null) {
            return [
                'display' => 'button',
                'label' => $this->evaluate($this->label, $ctx),
                'value' => null,
                'color' => null,
                'href' => null,
                'button' => $this->button($this->action)->serialize($ctx),
            ];
        }

        return [
            'display' => $this->display,
            'label' => $this->evaluate($this->label, $ctx),
            'value' => $this->evaluate($this->state, $ctx),
            'color' => $this->evaluate($this->color, $ctx),
            'href' => $this->evaluate($this->url, $ctx),
            'button' => null,
        ];
    }

    private function button(PageAction $action): DynamicButton
    {
        return DynamicButton::make("{$this->id()}.button")
            ->label($this->state)
            ->variant($action->isDestructive() ? 'destructive' : 'outline')
            ->dialog($this->dialog($action));
    }

    private function dialog(PageAction $action): DynamicDialog
    {
        $dialog = DynamicDialog::make($this->dialogId());

        if (($heading = $action->getModalHeading()) !== null) {
            $dialog->title($heading);
        }
        if (($description = $action->getModalDescription()) !== null) {
            $dialog->description($description);
        }
        if ($action->isSheet()) {
            $dialog->sheet();
        }
        if (($fields = $action->getFormFields()) !== []) {
            $dialog->formFields($fields);
        }

        $dialog->action($action->id());

        if (($confirm = $action->getConfirm()) !== null) {
            $dialog->confirm($confirm);
        }
        if (($confirmText = $action->getConfirmText()) !== null) {
            $dialog->confirmText($confirmText, $action->getConfirmField());
        }

        return $dialog;
    }

    private function dialogId(): string
    {
        $id = $this->id();
        $tail = str_contains($id, '.') ? substr((string) strrchr($id, '.'), 1) : $id;

        return "{$tail}-dialog";
    }
}
