<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\Http as HttpContract;

/**
 * RESERVED capability facade — resolving throws until the capability chokepoint lands.
 *
 * @see HttpContract
 */
final class Http extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HttpContract::class;
    }
}
