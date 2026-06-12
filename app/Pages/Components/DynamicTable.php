<?php

namespace App\Pages\Components;

use App\Pages\Schema\EvaluationContext;
use Closure;
use Forjed\InertiaTable\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Wraps an app/Tables inertia-table class. The schema node carries the SHELL only
 * (table id, header buttons, row actions, realtime config); the heavy table data
 * (columns, rows, pagination) ships as a sibling top-level prop keyed `tables:{id}`,
 * so realtime/interaction can partially reload just the data without rebuilding the
 * page schema.
 */
final class DynamicTable extends AbstractComponent
{
    /**
     * @var class-string|null
     */
    private ?string $tableClass = null;

    private ?Closure $query = null;

    /**
     * @var array<int, DynamicButton>
     */
    private array $headerButtons = [];

    /**
     * @var array<int, RowAction>
     */
    private array $rowActions = [];

    private ?string $realtime = null;

    /**
     * @var array<string, mixed>
     */
    private array $realtimeScope = [];

    private ?string $rowActionsControl = null;

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'table';
    }

    /**
     * @param  class-string  $tableClass
     */
    public function table(string $tableClass): self
    {
        $this->tableClass = $tableClass;

        return $this;
    }

    /**
     * @param  Closure(array<string, Model>): mixed  $query
     */
    public function query(Closure $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @param  array<int, DynamicButton>  $buttons
     */
    public function headerButtons(array $buttons): self
    {
        $this->headerButtons = $buttons;

        return $this;
    }

    /**
     * @param  array<int, RowAction>  $actions
     */
    public function rowActions(array $actions): self
    {
        $this->rowActions = $actions;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function realtime(string $prefix, array $scope = []): self
    {
        $this->realtime = $prefix;
        $this->realtimeScope = $scope;

        return $this;
    }

    /**
     * Render each row's actions via a registered row-actions control (for rich,
     * status-dependent menus the built-in rowActions can't express).
     */
    public function rowActionsControl(string $name): self
    {
        $this->rowActionsControl = $name;

        return $this;
    }

    /**
     * Build the inertia-table data array for the sibling `tables:{id}` prop.
     *
     * @param  array<string, Model>  $models
     * @return array<string, mixed>
     */
    public function buildData(array $models): array
    {
        $query = ($this->query)($models);

        /** @var Table $table */
        $table = $this->tableClass::make($query)->identifier($this->id());

        return $table->simplePaginate();
    }

    /**
     * @return array<int, \App\Pages\PageAction>
     */
    public function actions(): array
    {
        $actions = [];
        foreach ([...$this->headerButtons, ...$this->rowActions] as $node) {
            $actions = array_merge($actions, $node->actions());
        }

        return $actions;
    }

    /**
     * @return array<int, \App\Pages\DataEndpoint>
     */
    public function data(): array
    {
        $data = [];
        foreach ([...$this->headerButtons, ...$this->rowActions] as $node) {
            $data = array_merge($data, $node->data());
        }

        return $data;
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'tableId' => $this->id(),
            'headerButtons' => array_values(array_filter(array_map(fn (DynamicButton $b): ?array => $b->serialize($ctx), $this->headerButtons))),
            'rowActions' => array_values(array_filter(array_map(fn (RowAction $a): ?array => $a->serialize($ctx), $this->rowActions))),
            'realtime' => $this->realtime,
            'realtimeScope' => $this->realtimeScope,
            'rowActionsControl' => $this->rowActionsControl,
        ];
    }
}
