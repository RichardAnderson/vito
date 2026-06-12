<?php

namespace App\Tables;

use App\Models\CronJob;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\CopyableColumn;
use Forjed\InertiaTable\Columns\DateTimeColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class CronJobTable extends Table
{
    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->sortable(),
            CopyableColumn::make('command', 'Command'),
            TextColumn::make('user', 'User'),
            TextColumn::make('frequency', 'Frequency'),
            DateTimeColumn::make('created_at', 'Created at')->sortable(),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            Column::data('cronJob', fn (CronJob $cronJob): int => $cronJob->id),
            Column::data('server_id'),
            Column::data('site_id'),
            Column::data('status_value', fn (CronJob $cronJob): string => $cronJob->status->value),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'command', 'user'];
    }
}
