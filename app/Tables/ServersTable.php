<?php

namespace App\Tables;

use App\Models\Project;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ServersTable extends AbstractTable
{
    public function __construct(protected Project $project) {}

    public static function make(?Project $project = null): self
    {
        return new self($project ?? user()->currentProject);
    }

    public function query(): Builder|Relation
    {
        return $this->project->servers();
    }

    /**
     * @return array<Column>
     */
    public function columns(): array
    {
        return [
            Column::text('id', 'ID')
                ->sortable()
                ->hidden(),

            Column::link('name', 'Name', 'servers.show', ['server' => ':id'])
                ->sortable()
                ->searchable(),

            Column::text('ip', 'IP')
                ->sortable()
                ->searchable(),

            Column::date('created_at', 'Created at')
                ->sortable(),

            Column::status('status', 'Status', 'status_color')
                ->sortable(),

            Column::actions('servers.show', ['server' => ':id']),
        ];
    }

    /**
     * @return array{field: string|null, direction: string}
     */
    public function defaultSort(): array
    {
        return [
            'field' => 'created_at',
            'direction' => 'desc',
        ];
    }

    public function pageName(): string
    {
        return 'serversPage';
    }
}
