<?php

namespace Vito\Plugin\Contracts;

/**
 * The SDK-owned contract every Vito enum satisfies.
 *
 * Core's enum contract extends this, so every Vito enum implements it transitively and
 * generated Host API contracts can type enum-backed properties without referencing core
 * enums. Combine with the global \BackedEnum in PHPDoc to also expose `->value`.
 */
interface VitoEnum
{
    public function getColor(): string;

    public function getText(): string;
}
