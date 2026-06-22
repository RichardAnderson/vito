<?php

namespace App\Plugins;

use Vito\Plugin\Contracts\Registrars\ViewsRegistrar;

class RegisterViews implements ViewsRegistrar
{
    private const string CONFIG_KEY = 'plugins.views';

    public function __construct(
        private readonly string $name,
        private string $path = '',
    ) {}

    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function register(): void
    {
        if (empty($this->name) || empty($this->path)) {
            return;
        }

        $views = self::get();
        $views[$this->name] = $this->path;

        config([self::CONFIG_KEY => $views]);
    }

    public static function get(): array
    {
        return config(self::CONFIG_KEY) ?? [];
    }
}
