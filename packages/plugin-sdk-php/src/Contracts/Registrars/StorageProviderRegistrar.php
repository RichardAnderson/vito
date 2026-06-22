<?php

namespace Vito\Plugin\Contracts\Registrars;

use Vito\Plugin\DTOs\DynamicForm;

interface StorageProviderRegistrar
{
    public function label(string $label): static;

    public function handler(string $handler): static;

    public function form(DynamicForm $form): static;

    public function register(): void;
}
