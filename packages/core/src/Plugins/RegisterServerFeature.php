<?php

namespace App\Plugins;

use RuntimeException;

use Vito\Plugin\Contracts\Registrars\ServerFeatureRegistrar;

class RegisterServerFeature implements ServerFeatureRegistrar
{
    public function __construct(
        public string $name,
        public string $label = '',
        public string $description = ''
    ) {}

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function register(): void
    {
        $features = config('server.features') ?? [];

        if (isset($features[$this->name])) {
            throw new RuntimeException("Feature '{$this->name}' already exists");
        }

        $features[$this->name] = [
            'label' => $this->label,
            'description' => $this->description,
        ];

        config(['server.features' => $features]);
    }
}
