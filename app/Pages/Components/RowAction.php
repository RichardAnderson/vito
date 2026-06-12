<?php

namespace App\Pages\Components;

use App\Pages\Schema\EvaluationContext;
use Illuminate\Support\Str;

/**
 * A row-action dropdown item inside a DynamicTable. Either dispatches a named page
 * action or opens a dialog. Row data interpolates into action/dialog params via
 * `:column` placeholders; `visibleWhen` gates per-row visibility client-side
 * (row context is only known client-side).
 */
final class RowAction extends AbstractComponent
{
    private string $label;

    private ?string $icon = null;

    private ?string $action = null;

    private ?DynamicDialog $dialog = null;

    private ?string $confirm = null;

    private bool $destructive = false;

    /**
     * @var array{column: string, value: mixed}|null
     */
    private ?array $visibleWhen = null;

    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    public static function make(string $label): self
    {
        $instance = new self(Str::slug($label));
        $instance->label = $label;

        return $instance;
    }

    public function type(): string
    {
        return 'row-action';
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function action(string $action, array $params = []): self
    {
        $this->action = $action;
        $this->params = $params;

        return $this;
    }

    public function dialog(DynamicDialog $dialog): self
    {
        $this->dialog = $dialog;

        return $this;
    }

    public function confirm(string $message): self
    {
        $this->confirm = $message;

        return $this;
    }

    public function destructive(bool $destructive = true): self
    {
        $this->destructive = $destructive;

        return $this;
    }

    public function visibleWhen(string $column, mixed $value): self
    {
        $this->visibleWhen = ['column' => $column, 'value' => $value];

        return $this;
    }

    /**
     * @return array<int, \App\Pages\PageAction>
     */
    public function actions(): array
    {
        return $this->dialog?->actions() ?? [];
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
            'label' => $this->label,
            'icon' => $this->icon,
            'action' => $this->action,
            'params' => $this->params,
            'dialog' => $this->dialog?->serialize($ctx),
            'confirm' => $this->confirm,
            'destructive' => $this->destructive,
            'visibleWhen' => $this->visibleWhen,
        ];
    }
}
