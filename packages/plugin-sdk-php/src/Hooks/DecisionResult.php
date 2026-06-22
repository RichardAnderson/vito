<?php

namespace Vito\Plugin\Hooks;

/**
 * The outcome of a DecisionHook: the folded boolean plus, when denied, the reason and an opaque
 * token for the listener source that produced the winning vote (for audit / UI).
 */
final class DecisionResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?object $deniedBy = null,
    ) {}
}
