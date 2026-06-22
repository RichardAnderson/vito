<?php

namespace Vito\Plugin\Contracts\Registrars;

interface ViewsRegistrar
{
    public function path(string $path): static;

    public function register(): void;
}
