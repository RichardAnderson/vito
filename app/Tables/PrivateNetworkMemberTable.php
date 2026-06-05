<?php

namespace App\Tables;

use App\Models\PrivateNetworkMember;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class PrivateNetworkMemberTable extends Table
{
    protected array $tableSettings = ['realtime' => 'private-network-member'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query->with('server')->latest();
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('server_name', 'Server')
                ->value(fn (PrivateNetworkMember $member): ?string => $member->server?->name),
            TextColumn::make('overlay_ip', 'Overlay IP')->sortable(),
            TextColumn::make('interface', 'Interface')->sortable(),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            Column::data('server_id'),
            Column::data('private_network_id'),
            ActionsColumn::make(),
        ];
    }
}
