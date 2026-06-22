<?php

namespace App\Plugins;

use Vito\Plugin\DTOs\DynamicForm;

use Vito\Plugin\Contracts\Registrars\DnsProviderRegistrar;

class RegisterDNSProvider implements DnsProviderRegistrar
{
    public function __construct(
        private string $name,
        private string $label = '',
        private string $handler = '',
        private ?DynamicForm $form = null,
        private ?DynamicForm $editForm = null,
        private array $proxyTypes = [],
        private bool $supportsCreatedAt = true,
    ) {}

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

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

    public function editForm(DynamicForm $editForm): static
    {
        $this->editForm = $editForm;

        return $this;
    }

    public function proxyTypes(array $proxyTypes): static
    {
        $this->proxyTypes = $proxyTypes;

        return $this;
    }

    public function supportsCreatedAt(bool $supportsCreatedAt): static
    {
        $this->supportsCreatedAt = $supportsCreatedAt;

        return $this;
    }

    public function register(): void
    {
        $providers = config('dns-provider.providers');

        $providers[$this->name] = [
            'label' => $this->label,
            'handler' => $this->handler,
            'form' => $this->form ? $this->form->toArray() : [],
            'edit_form' => $this->editForm ? $this->editForm->toArray() : [],
            'proxy_types' => $this->proxyTypes,
            'supports_created_at' => $this->supportsCreatedAt,
        ];

        config(['dns-provider.providers' => $providers]);
    }
}
