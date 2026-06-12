<?php

namespace App\Pages;

use App\Plugins\RegisterExtensionAction;

/**
 * Boot-time registry of standalone plugin extension actions (those with no owning
 * page). Keyed by "{plugin}.{action}". Scoped, re-populated each request from plugin
 * boot(), so a disabled plugin's stale cached route resolves to null → 404.
 */
final class ExtensionActionRegistry
{
    /**
     * @var array<string, RegisterExtensionAction>
     */
    private array $actions = [];

    private string $currentPlugin = 'core';

    public function setCurrentPlugin(string $plugin): void
    {
        $this->currentPlugin = $plugin;
    }

    public function resetCurrentPlugin(): void
    {
        $this->currentPlugin = 'core';
    }

    public function currentPlugin(): string
    {
        return $this->currentPlugin;
    }

    public function add(RegisterExtensionAction $action): void
    {
        $action->attribute($this->currentPlugin);
        $this->actions[$this->currentPlugin.'.'.$action->name()] = $action;
    }

    /**
     * @return array<string, RegisterExtensionAction>
     */
    public function all(): array
    {
        return $this->actions;
    }

    public function get(string $plugin, string $action): ?RegisterExtensionAction
    {
        return $this->actions[$plugin.'.'.$action] ?? null;
    }
}
