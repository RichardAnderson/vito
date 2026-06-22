<?php

namespace Vito\Plugin\Hooks;

final class HookListener
{
    /**
     * @param  callable  $callback  The plugin-supplied listener.
     * @param  object|null  $source  An opaque token identifying who registered it (the host tags this for attribution).
     */
    public function __construct(
        public readonly mixed $callback,
        public readonly ?object $source = null,
    ) {}
}
