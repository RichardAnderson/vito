<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Broadcast as BroadcastContract;

/**
 * @method static void dispatch(int $projectId, string $type, array $data)
 *
 * @see BroadcastContract
 */
final class Broadcast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BroadcastContract::class;
    }
}
