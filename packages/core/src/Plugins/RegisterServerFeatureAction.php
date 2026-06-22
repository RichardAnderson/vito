<?php

namespace App\Plugins;

use Vito\Plugin\DTOs\DynamicForm;
use RuntimeException;

use Vito\Plugin\Contracts\Registrars\ServerFeatureActionRegistrar;

class RegisterServerFeatureAction implements ServerFeatureActionRegistrar
{
    public function __construct(
        public string $feature,
        public string $name,
        public string $label = '',
        public string $handler = '',
        public ?DynamicForm $form = null,
    ) {}

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function handler(string $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function form(DynamicForm $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function register(): void
    {
        $feature = config('server.features.'.$this->feature);

        if (! $feature) {
            throw new RuntimeException("Feature '{$this->feature}' not found");
        }

        $actions = $feature['actions'] ?? [];
        if (isset($actions[$this->name])) {
            throw new RuntimeException("Action '{$this->name}' already exists for feature '{$this->feature}'");
        }

        $actions[$this->name] = [
            'label' => $this->label,
            'handler' => $this->handler,
            'form' => $this->form ? $this->form->toArray() : [],
        ];

        config(['server.features.'.$this->feature.'.actions' => $actions]);
    }
}
