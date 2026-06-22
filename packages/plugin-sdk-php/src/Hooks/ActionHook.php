<?php

namespace Vito\Plugin\Hooks;

use Throwable;
use Vito\Plugin\Facades\Hooks;

/**
 * A run-all, no-return extension point in core's flow (≈ WordPress do_action). Core fires it; every
 * registered listener runs. A throwing listener is reported and skipped — it can never break the flow.
 *
 * Subclasses declare the inputs as an SDK-typed constructor; that signature IS the contract. Listeners
 * receive those inputs positionally.
 */
abstract class ActionHook
{
    public static function register(callable $listener): void
    {
        Hooks::register(static::class, $listener);
    }

    public static function execute(mixed ...$args): void
    {
        foreach (Hooks::listeners(static::class) as $listener) {
            try {
                ($listener->callback)(...$args);
            } catch (Throwable $exception) {
                Hooks::report($exception, $listener->source);
            }
        }
    }
}
