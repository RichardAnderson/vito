<?php

namespace App\Pages\Server;

use App\Models\Server;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\ServerArea;
use App\Pages\Components\Card;
use App\Pages\Components\DynamicCardRow;
use App\Pages\Components\Page;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;

/**
 * Phase 0 proof-of-concept page. Deletable — it exists to exercise the full
 * pipeline (area resolution, binding, canView, named route, recursive renderer)
 * with zero production-page risk. Remove once a real core page is migrated.
 */
final class ServerPocPage extends AbstractPage
{
    public static function id(): string
    {
        return 'server.poc';
    }

    public function area(): AbstractArea
    {
        return app(ServerArea::class);
    }

    public function slug(): string
    {
        return '_poc';
    }

    public function navTitle(): ?string
    {
        return 'Framework PoC';
    }

    public function schema(): array
    {
        return [
            Page::make('server-poc')
                ->title('Framework Page PoC')
                ->add(
                    Card::make('details-card')
                        ->title('Server details')
                        ->add(
                            DynamicCardRow::copyable('details-card.name', 'Name')->value(fn (Server $server) => $server->name),
                            DynamicCardRow::copyable('details-card.ip', 'IP address')->value(fn (Server $server) => $server->ip),
                            DynamicCardRow::badge('details-card.status', 'Status')
                                ->value(fn (Server $server) => $server->status->getText())
                                ->color(fn (Server $server) => $server->status->getColor()),
                        ),
                ),
        ];
    }

    public function headless(): array
    {
        return [
            PageAction::make('ping')
                ->authorize(fn (User $user, Server $server): bool => $user->can('update', $server))
                ->run(fn (): mixed => back()->with('success', 'pong')),

            DataEndpoint::make('info')
                ->public()
                ->resolve(fn (Server $server): array => ['name' => $server->name]),
        ];
    }
}
