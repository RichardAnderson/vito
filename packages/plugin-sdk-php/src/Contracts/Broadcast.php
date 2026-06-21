<?php

namespace Vito\Plugin\Contracts;

/**
 * Capability facade key for broadcasting realtime socket events to a project's clients.
 *
 * Bound by core to an adapter over the host SocketEvent dispatcher.
 */
interface Broadcast
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(int $projectId, string $type, array $data): void;
}
