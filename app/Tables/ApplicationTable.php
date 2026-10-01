<?php

namespace App\Tables;

use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\DateTimeColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class ApplicationTable extends Table
{
    protected array $tableSettings = ['realtime' => 'application'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->withCount('environments')->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->sortable(),
            TextColumn::make('repository', 'Repository')->fallback('-'),
            TextColumn::make('environments_count', 'Environments'),
            EnumColumn::make('status', 'Status')->sortable(),
            DateTimeColumn::make('created_at', 'Created At')->sortable()->toLocal(),
            Column::data('id'),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'repository'];
    }
}
