<?php

namespace Vito\Plugin\Contracts;

/**
 * Capability facade key for sending notifications through the operator's configured channels.
 *
 * Bound by core to the host notifier.
 */
interface Notifications
{
    public function send(object $notifiable, object $notification): void;
}
