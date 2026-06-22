<?php

namespace App\Plugins\Hooks;

use App\Models\Plugin;
use App\Models\PluginError;
use Throwable;
use Vito\Plugin\Contracts\HookRegistry as HookRegistryContract;
use Vito\Plugin\Hooks\HookListener;

final class HookRegistry implements HookRegistryContract
{
    /**
     * @var array<string, array<int, HookListener>>
     */
    private array $listeners = [];

    private ?object $source = null;

    public function register(string $hook, callable $listener): void
    {
        $this->listeners[$hook][] = new HookListener($listener, $this->source);
    }

    public function listeners(string $hook): array
    {
        return $this->listeners[$hook] ?? [];
    }

    public function report(Throwable $exception, ?object $source): void
    {
        if ($source instanceof Plugin) {
            PluginError::createFromException($exception, $source);

            return;
        }

        report($exception);
    }

    public function usingSource(?object $source): void
    {
        $this->source = $source;
    }

    public function flush(): void
    {
        $this->listeners = [];
        $this->source = null;
    }
}
