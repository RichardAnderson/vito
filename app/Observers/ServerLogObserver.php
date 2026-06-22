<?php

namespace App\Observers;

use App\DTOs\SocketEventDTO;
use App\Events\SocketEvent;
use App\Http\Resources\ServerLogResource;
use App\Models\ServerLog;
use Exception;
use Illuminate\Support\Facades\Log;

final class ServerLogObserver
{
    public function created(ServerLog $log): void
    {
        try {
            SocketEvent::dispatch(new SocketEventDTO(
                projectId: $log->server->project_id,
                type: 'server-log.created',
                data: new ServerLogResource($log),
            ));
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
        }
    }
}
