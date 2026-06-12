<?php

namespace App\Pages\Areas;

use App\Http\Resources\ServerResource;
use App\Http\Resources\SiteResource;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\Binding;

final class SiteArea extends AbstractArea
{
    public static function id(): string
    {
        return 'site';
    }

    public function routePrefix(): string
    {
        return 'servers/{server}/sites/{site}';
    }

    public function bindings(): array
    {
        return [
            Binding::root('server', Server::class),
            Binding::make('site', Site::class, scopedTo: 'server'),
        ];
    }

    public function canView(User $user, array $models): bool
    {
        return $user->can('view', [$models['site'], $models['server']]);
    }

    public function layout(): string
    {
        return 'site';
    }

    public function sharedProps(array $models): array
    {
        return [
            'server' => (new ServerResource($models['server']))->resolve(),
            'site' => (new SiteResource($models['site']))->resolve(),
        ];
    }
}
