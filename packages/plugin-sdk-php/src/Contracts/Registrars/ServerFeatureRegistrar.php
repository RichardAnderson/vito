<?php

namespace Vito\Plugin\Contracts\Registrars;

interface ServerFeatureRegistrar
{
    public function label(string $label): static;

    public function description(string $description): static;

    public function register(): void;
}
