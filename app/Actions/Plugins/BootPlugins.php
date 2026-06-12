<?php

namespace App\Actions\Plugins;

use App\Models\PluginError;
use App\Pages\ExtensionActionRegistry;
use App\Pages\ExtensionRegistry;
use Throwable;

final readonly class BootPlugins
{
    public function __construct(
        private GetPluginInstance $getInstance,
        private PluginCache $cache,
        private ExtensionRegistry $extensions,
        private ExtensionActionRegistry $extensionActions,
    ) {}

    public function handle(): void
    {
        $plugins = $this->cache->get();
        $booted = [];

        foreach ($plugins as $plugin) {
            try {
                $instance = $this->getInstance->handle($plugin);
                $pluginKey = $plugin->name ?? (string) $plugin->id;
                $this->extensions->setCurrentPlugin($pluginKey);
                $this->extensionActions->setCurrentPlugin($pluginKey);
                $instance->boot();
                $booted[] = $plugin;
            } catch (Throwable $exception) {
                $plugin->is_enabled = false;
                $plugin->save();
                PluginError::createFromException($exception, $plugin);
            } finally {
                $this->extensions->resetCurrentPlugin();
                $this->extensionActions->resetCurrentPlugin();
            }
        }

        // Where we have booted fewer plugins than where loaded
        // collect the plugins and set the cache for next time
        if (count($booted) < count($plugins)) {
            $this->cache->set(collect($booted));
        }
    }
}
