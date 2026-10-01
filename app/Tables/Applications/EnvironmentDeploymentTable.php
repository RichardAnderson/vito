<?php

namespace App\Tables\Applications;

use App\Models\EnvironmentDeployment;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\DateTimeColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class EnvironmentDeploymentTable extends Table
{
    protected array $tableSettings = ['realtime' => 'environment-deployment'];

    protected string $defaultSort = '-created_at';

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->withCount('deployments');
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('id', 'ID')->sortable(),
            Column::make('commit', 'Commit')
                ->value(fn (EnvironmentDeployment $run): string => $run->commit_id !== null
                    ? substr($run->commit_id, 0, 7)
                    : '-'),
            TextColumn::make('release', 'Release')->fallback('-'),
            TextColumn::make('deployments_count', 'Components'),
            EnumColumn::make('status', 'Status')->sortable(),
            DateTimeColumn::make('created_at', 'Started')->sortable()->toLocal(),
            Column::data('id'),
            ActionsColumn::make(),
        ];
    }
}
