<?php

namespace App\Plugins;

use Vito\Plugin\Contracts\Registrars\WorkflowActionRegistrar;

class RegisterWorkflowAction implements WorkflowActionRegistrar
{
    public function __construct(
        public string $name,
        public string $label = '',
        public string $description = '',
        public string $category = '',
        public string $handler = '',
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

    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function handler(string $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function register(): void
    {
        $actions = config('workflow.actions');

        $actions[$this->name] = [
            'label' => $this->label,
            'description' => $this->description,
            'category' => $this->category,
            'handler' => $this->handler,
        ];

        config(['workflow.actions' => $actions]);
    }
}
