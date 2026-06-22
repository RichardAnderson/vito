<?php

namespace App\Broadcasting;

use App\Contracts\SiteProgressBroadcaster as SiteProgressBroadcasterContract;
use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;
use App\Http\Resources\SiteResource;
use App\Models\Site;

final class SiteProgressBroadcaster implements SiteProgressBroadcasterContract
{
    public function broadcast(Site $site): void
    {
        SocketEvent::dispatch(new SocketEventDTO(
            projectId: $site->server->project_id,
            type: 'site.updated',
            data: new SiteResource($site),
        ));
    }
}
