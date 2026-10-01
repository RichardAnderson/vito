<?php

namespace App\Tables\Applications;

use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use App\Models\EnvironmentDomain;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class EnvironmentDomainTable extends Table
{
    protected array $tableSettings = ['realtime' => 'environment-domain'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->with(['ssl', 'redirectTo'])->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('host', 'Host')->sortable(),
            EnumColumn::make('type', 'Type'),
            Column::make('redirects_to', 'Redirects To')
                ->value(fn (EnvironmentDomain $domain): string => $domain->redirectTo->host ?? '-'),
            TextColumn::make('ssl_method', 'SSL'),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['host'];
    }
}
