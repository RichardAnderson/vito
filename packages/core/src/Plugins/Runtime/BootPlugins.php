<?php

namespace App\Plugins\Runtime;

use App\Models\PluginError;
use App\Plugins\Hooks\HookRegistry;
use Throwable;

final readonly class BootPlugins
{
    public function __construct(
        private GetPluginInstance $getInstance,
        private PluginCache $cache,
        private HookRegistry $hooks,
    ) {}

    public function handle(): void
    {
        $this->hooks->flush();

        $plugins = $this->cache->get();
        $booted = [];

        foreach ($plugins as $plugin) {
            try {
                $instance = $this->getInstance->handle($plugin);

                $this->hooks->usingSource($plugin);
                try {
                    $instance->boot();
                } finally {
                    $this->hooks->usingSource(null);
                }

                $booted[] = $plugin;
            } catch (Throwable $exception) {
                $plugin->is_enabled = false;
                $plugin->save();
                PluginError::createFromException($exception, $plugin);
            }
        }

        // Where we have booted fewer plugins than where loaded
        // collect the plugins and set the cache for next time
        if (count($booted) < count($plugins)) {
            $this->cache->set(collect($booted));
        }
    }
}
