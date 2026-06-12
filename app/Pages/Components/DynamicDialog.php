<?php

namespace App\Pages\Components;

use App\DTOs\DynamicForm;
use App\Pages\Components\Forms\Field;
use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A dialog or side sheet routed through the central dialog registry. Body can be a
 * form, a log view, or a code editor. References a page action for its server side
 * (by id/closure or an attached PageAction object); type-to-confirm and nested
 * confirms are supported via the bounded dialog stack.
 */
final class DynamicDialog extends AbstractComponent
{
    private string|Closure|null $title = null;

    private string|Closure|null $description = null;

    private bool $sheet = false;

    private DynamicForm|Closure|null $form = null;

    /**
     * @var array<int, Field>
     */
    private array $formFields = [];

    private string|Closure|null $action = null;

    private ?PageAction $actionObject = null;

    private string|Closure|null $confirm = null;

    private string|Closure|null $confirmText = null;

    private string $confirmField = 'confirmation';

    private ?DynamicLogView $logView = null;

    private ?DynamicCodeEditor $editor = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'dialog';
    }

    public function title(string|Closure $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string|Closure $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function sheet(bool $sheet = true): self
    {
        $this->sheet = $sheet;

        return $this;
    }

    public function form(DynamicForm|Closure $form): self
    {
        $this->form = $form;

        return $this;
    }

    /**
     * Filament-style form fields, resolved against the request context at serialize.
     *
     * @param  array<int, Field>  $fields
     */
    public function formFields(array $fields): self
    {
        $this->formFields = $fields;

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

    public function confirm(string|Closure $message): self
    {
        $this->confirm = $message;

        return $this;
    }

    public function confirmText(string|Closure $value, string $field = 'confirmation'): self
    {
        $this->confirmText = $value;
        $this->confirmField = $field;

        return $this;
    }

    public function logView(DynamicLogView $logView): self
    {
        $this->logView = $logView;

        return $this;
    }

    public function editor(DynamicCodeEditor $editor): self
    {
        $this->editor = $editor;

        return $this;
    }

    /**
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return array_merge(
            $this->actionObject !== null ? [$this->actionObject] : [],
            $this->editor?->actions() ?? [],
        );
    }

    /**
     * @return array<int, \App\Pages\DataEndpoint>
     */
    public function data(): array
    {
        return $this->editor?->data() ?? [];
    }

    protected function props(EvaluationContext $ctx): array
    {
        $form = $this->formFields !== []
            ? DynamicForm::make(array_map(fn (Field $field): \App\DTOs\DynamicField => $field->resolve($ctx), $this->formFields))
            : $this->evaluate($this->form, $ctx);

        return [
            'title' => $this->evaluate($this->title, $ctx),
            'description' => $this->evaluate($this->description, $ctx),
            'sheet' => $this->sheet,
            'form' => $form?->toArray(),
            'action' => $this->evaluate($this->action, $ctx),
            'confirm' => $this->evaluate($this->confirm, $ctx),
            'confirmText' => $this->evaluate($this->confirmText, $ctx),
            'confirmField' => $this->confirmField,
            'logView' => $this->logView?->serialize($ctx),
            'editor' => $this->editor?->serialize($ctx),
        ];
    }
}
