<?php

namespace Vito\Plugin\Contracts\Registrars;

interface WorkflowActionRegistrar
{
    public function label(string $label): static;

    public function description(string $description): static;

    public function category(string $category): static;

    public function handler(string $handler): static;

    public function register(): void;
}
