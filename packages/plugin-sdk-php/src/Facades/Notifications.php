<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Notifications as NotificationsContract;

/**
 * @method static void send(object $notifiable, object $notification)
 *
 * @see NotificationsContract
 */
final class Notifications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationsContract::class;
    }
}
