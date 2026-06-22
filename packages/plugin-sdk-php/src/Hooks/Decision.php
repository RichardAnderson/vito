<?php

namespace Vito\Plugin\Hooks;

/**
 * A decision-hook listener may return one of these instead of a bare bool, to attach a
 * human-readable reason (shown in the UI / audit log — "which plugin blocked and why").
 */
final class Decision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
    ) {}

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }

    public static function allow(?string $reason = null): self
    {
        return new self(true, $reason);
    }
}
