<?php

namespace App\Tables\Applications;

use App\Models\Component;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class ComponentTable extends Table
{
    protected array $tableSettings = ['realtime' => 'component'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->with(['server', 'functions'])->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->sortable(),
            EnumColumn::make('type', 'Type'),
            Column::make('server', 'Server')
                ->value(fn (Component $component): string => $component->server->name),
            Column::make('functions', 'Functions')
                ->value(fn (Component $component): string => $component->functions
                    ->map(fn ($function): string => $function->label())
                    ->implode(', ') ?: '-'),
            TextColumn::make('php_version', 'PHP')->fallback('-'),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }
}
