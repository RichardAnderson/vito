<?php

namespace Vito\Plugin\Hooks;

use Vito\Plugin\Contracts\Site;

/**
 * Fired just before a deployment runs. Listeners run preflight side effects; they cannot block
 * (use {@see ShouldDeploy} to veto).
 */
final class PreDeploy extends ActionHook
{
    public function __construct(public readonly Site $site) {}
}
