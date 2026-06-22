<?php

namespace Vito\Plugin\Contracts\Registrars;

use Vito\Plugin\DTOs\DynamicForm;

interface DnsProviderRegistrar
{
    public function label(string $label): static;

    public function handler(string $handler): static;

    public function form(DynamicForm $form): static;

    public function editForm(DynamicForm $editForm): static;

    /**
     * @param  array<int, string>  $proxyTypes
     */
    public function proxyTypes(array $proxyTypes): static;

    public function supportsCreatedAt(bool $supportsCreatedAt): static;

    public function register(): void;
}
