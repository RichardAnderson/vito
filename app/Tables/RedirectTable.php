<?php

namespace App\Tables;

use App\Models\Redirect;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\DateTimeColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class RedirectTable extends Table
{
    protected array $tableSettings = ['realtime' => 'redirect'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->with('site:id,server_id')->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('from', 'From')->sortable(),
            TextColumn::make('to', 'To')->sortable(),
            Column::make('mode', 'Mode')->component('redirect-mode'),
            DateTimeColumn::make('created_at', 'Created at')->sortable(),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            Column::data('server_id', fn (Redirect $redirect): int => $redirect->site->server_id),
            Column::data('site_id'),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['from', 'to'];
    }
}
