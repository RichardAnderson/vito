<?php

namespace App\Plugins;

use Vito\Plugin\DTOs\DynamicForm;

use Vito\Plugin\Contracts\Registrars\NotificationChannelRegistrar;

class RegisterNotificationChannel implements NotificationChannelRegistrar
{
    public function __construct(
        private string $name,
        private string $label = '',
        private string $handler = '',
        private ?DynamicForm $form = null,
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

    public function register(): void
    {
        $providers = config('notification-channel.providers');

        $providers[$this->name] = [
            'label' => $this->label,
            'handler' => $this->handler,
            'form' => $this->form ? $this->form->toArray() : [],
        ];

        config(['notification-channel.providers' => $providers]);
    }
}
