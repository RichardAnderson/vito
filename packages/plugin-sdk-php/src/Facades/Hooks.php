<?php

namespace Vito\Plugin\Facades;

use Illuminate\Support\Facades\Facade;
use Vito\Plugin\Contracts\HookRegistry;

/**
 * @method static void register(string $hook, callable $listener)
 * @method static \Vito\Plugin\Hooks\HookListener[] listeners(string $hook)
 * @method static void report(\Throwable $exception, ?object $source)
 *
 * @see HookRegistry
 */
final class Hooks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookRegistry::class;
    }
}
