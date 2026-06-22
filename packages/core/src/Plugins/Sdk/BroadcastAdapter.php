<?php

namespace App\Plugins\Sdk;

use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;
use Vito\Plugin\Contracts\Broadcast;

final class BroadcastAdapter implements Broadcast
{
    public function dispatch(int $projectId, string $type, array $data): void
    {
        SocketEvent::dispatch(new SocketEventDTO($projectId, $type, $data));
    }
}
