<?php

namespace App\Pages\Components;

use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A Monaco-backed editor (usually opened in a sheet) bound to a data endpoint for
 * load, an action for save, and an optional POST data endpoint for preview (unsaved
 * buffer in, rendered output out). Each binding may be a string id or an attached
 * DataEndpoint/PageAction object, which is then harvested into the page.
 */
final class DynamicCodeEditor extends AbstractComponent
{
    private string|DataEndpoint|null $load = null;

    private string|PageAction|null $save = null;

    private string|DataEndpoint|null $preview = null;

    private string|PageAction|null $reset = null;

    private string|Closure|null $info = null;

    private string|Closure $language = 'plaintext';

    private bool $readonly = false;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'code-editor';
    }

    public function load(string|DataEndpoint $endpoint): self
    {
        $this->load = $endpoint;

        return $this;
    }

    public function save(string|PageAction $action): self
    {
        $this->save = $action;

        return $this;
    }

    public function preview(string|DataEndpoint $endpoint): self
    {
        $this->preview = $endpoint;

        return $this;
    }

    public function reset(string|PageAction $action): self
    {
        $this->reset = $action;

        return $this;
    }

    public function info(string|Closure $info): self
    {
        $this->info = $info;

        return $this;
    }

    public function language(string|Closure $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function readonly(bool $readonly = true): self
    {
        $this->readonly = $readonly;

        return $this;
    }

    /**
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return array_values(array_filter([
            $this->save instanceof PageAction ? $this->save : null,
            $this->reset instanceof PageAction ? $this->reset : null,
        ]));
    }

    /**
     * @return array<int, DataEndpoint>
     */
    public function data(): array
    {
        return array_values(array_filter([
            $this->load instanceof DataEndpoint ? $this->load : null,
            $this->preview instanceof DataEndpoint ? $this->preview : null,
        ]));
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'load' => $this->reference($this->load),
            'save' => $this->reference($this->save),
            'preview' => $this->reference($this->preview),
            'reset' => $this->reference($this->reset),
            'info' => $this->evaluate($this->info, $ctx),
            'language' => $this->evaluate($this->language, $ctx),
            'readonly' => $this->readonly,
        ];
    }

    private function reference(string|DataEndpoint|PageAction|null $binding): ?string
    {
        return $binding instanceof DataEndpoint || $binding instanceof PageAction
            ? $binding->id()
            : $binding;
    }
}
