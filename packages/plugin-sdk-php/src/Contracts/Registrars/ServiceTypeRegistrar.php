<?php

namespace Vito\Plugin\Contracts\Registrars;

use Vito\Plugin\DTOs\DynamicForm;

interface ServiceTypeRegistrar
{
    public function type(string $type): static;

    public function unit(string $unit): static;

    public function label(string $label): static;

    public function handler(string $handler): static;

    public function form(DynamicForm $form): static;

    /**
     * @param  array<int, string>  $versions
     */
    public function versions(array $versions): static;

    /**
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): static;

    /**
     * @param  array<int, array{name: string, path: string, sudo: bool}>  $configPaths
     */
    public function configPaths(array $configPaths): static;

    public function register(): void;
}
