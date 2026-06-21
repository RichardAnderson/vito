<?php

namespace Tests\Fixtures\Plugins\Example;

use Vito\Plugin\PluginInterface;

/**
 * Reference plugin proving the Vito Plugin SDK end-to-end:
 * it implements the SDK lifecycle interface directly (not the core AbstractPlugin),
 * and its action (see Actions/Ping) consumes the Host API contracts + capability facades.
 */
final class Plugin implements PluginInterface
{
    public function boot(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function install(): void {}

    public function uninstall(): void {}

    public function getName(): string
    {
        return 'Example';
    }

    public function getDescription(): string
    {
        return 'Reference plugin proving the Vito Plugin SDK end-to-end.';
    }
}
