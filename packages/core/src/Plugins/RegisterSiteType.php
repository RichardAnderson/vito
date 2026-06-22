<?php

namespace App\Plugins;

use Vito\Plugin\DTOs\DynamicForm;

use Vito\Plugin\Contracts\Registrars\SiteTypeRegistrar;

class RegisterSiteType implements SiteTypeRegistrar
{
    public function __construct(
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
        $types = config('site.types');

        $types[$this->name] = [
            'label' => $this->label,
            'handler' => $this->handler,
            'form' => $this->form ? $this->form->toArray() : [],
        ];

        config(['site.types' => $types]);
    }
}
