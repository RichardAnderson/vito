<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Storage as StorageContract;

/**
 * RESERVED capability facade — resolving throws until the capability chokepoint lands.
 *
 * @see StorageContract
 */
final class Storage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StorageContract::class;
    }
}
