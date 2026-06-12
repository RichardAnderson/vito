<?php

namespace App\Pages\Areas;

use App\Http\Resources\ServerResource;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\Binding;
use App\Models\Server;

final class ServerArea extends AbstractArea
{
    public static function id(): string
    {
        return 'server';
    }

    public function routePrefix(): string
    {
        return 'servers/{server}';
    }

    public function bindings(): array
    {
        return [
            Binding::root('server', Server::class),
        ];
    }

    public function canView(User $user, array $models): bool
    {
        return $user->can('view', $models['server']);
    }

    public function layout(): string
    {
        return 'server';
    }

    public function sharedProps(array $models): array
    {
        return [
            'server' => (new ServerResource($models['server']))->resolve(),
        ];
    }
}
