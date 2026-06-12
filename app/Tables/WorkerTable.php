<?php

namespace App\Tables;

use App\Models\Site;
use App\Models\Worker;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\CopyableColumn;
use Forjed\InertiaTable\Columns\DateTimeColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class WorkerTable extends Table
{
    protected array $tableSettings = ['realtime' => 'worker'];

    protected ?Site $site = null;

    public function forSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->with('site:id,server_id,type_data')->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('name', 'Name')->sortable(),
            CopyableColumn::make('command', 'Command'),
            TextColumn::make('user', 'User')->sortable(),
            Column::make('numprocs', 'Processes'),
            DateTimeColumn::make('created_at', 'Created at')->sortable(),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            Column::data('server_id'),
            Column::data('site_id'),
            Column::data('status_value', fn (Worker $worker): string => $worker->status->value),
            Column::data('is_site_bootstrap', fn (Worker $worker): bool => $worker->isSiteBootstrap()),
            ActionsColumn::make(),
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'command', 'user'];
    }
}
