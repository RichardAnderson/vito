<?php

namespace Vito\Plugin\Contracts\Registrars;

use Vito\Plugin\DTOs\DynamicForm;

interface ServerProviderRegistrar
{
    public function label(string $label): static;

    public function handler(string $handler): static;

    public function form(DynamicForm $form): static;

    public function defaultUser(string $defaultUser): static;

    public function register(): void;
}
