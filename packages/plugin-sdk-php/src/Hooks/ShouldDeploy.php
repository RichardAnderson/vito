<?php

namespace Vito\Plugin\Hooks;

use Vito\Plugin\Contracts\Site;

/**
 * Veto point for a deployment. Default is to proceed; any listener returning false (or
 * Decision::deny(reason)) blocks the deploy. Listeners must be cheap, pure predicates.
 */
final class ShouldDeploy extends DecisionHook
{
    protected bool $default = true;

    public function __construct(public readonly Site $site) {}
}
