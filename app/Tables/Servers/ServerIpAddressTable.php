<?php

namespace App\Tables\Servers;

use App\Models\ServerIpAddress;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Columns\ActionsColumn;
use Forjed\InertiaTable\Columns\EnumColumn;
use Forjed\InertiaTable\Columns\TextColumn;
use Forjed\InertiaTable\Table;

class ServerIpAddressTable extends Table
{
    protected array $tableSettings = ['realtime' => 'server-ip'];

    protected function query(): void
    {
        $this->perPage = config('web.pagination_size');
        $this->query
            ->with('server.privateNetworkMembers.privateNetwork')
            ->orderByDesc('is_primary')
            ->orderBy('ip');
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('ip', 'IP Address')->sortable(),
            EnumColumn::make('family', 'Family')->sortable(),
            TextColumn::make('interface', 'Interface')->fallback('-')->sortable(),
            TextColumn::make('network', 'Network')
                ->fallback('-')
                ->value(fn (ServerIpAddress $ip): ?string => $ip->interface === null ? null : $ip->server
                    ->privateNetworkMembers
                    ->firstWhere('interface', $ip->interface)
                    ?->privateNetwork
                    ?->name),
            EnumColumn::make('type', 'Type')->sortable(),
            EnumColumn::make('status', 'Status')->sortable(),
            Column::data('id'),
            Column::data('server_id'),
            Column::data('is_managed'),
            Column::data('is_primary'),
            ActionsColumn::make(),
        ];
    }
}
