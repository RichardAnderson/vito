<?php

namespace Vito\Plugin\Contracts;

use Throwable;
use Vito\Plugin\Hooks\HookListener;

interface HookRegistry
{
    public function register(string $hook, callable $listener): void;

    /**
     * @return array<int, HookListener>
     */
    public function listeners(string $hook): array;

    public function report(Throwable $exception, ?object $source): void;
}
