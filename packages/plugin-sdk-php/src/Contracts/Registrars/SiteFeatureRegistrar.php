<?php

namespace Vito\Plugin\Contracts\Registrars;

interface SiteFeatureRegistrar
{
    public function label(string $label): static;

    public function description(string $description): static;

    public function register(): void;
}
