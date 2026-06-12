<?php

namespace App\Plugins;

use App\Pages\AbstractPage;
use App\Pages\PageRegistry;

/**
 * Plugin SDK hook: register a framework page into an existing area.
 *
 * Registration must be side-effect-free beyond adding to the registry — it runs
 * inside the boot pass that also drives dynamic route registration.
 */
class RegisterPage
{
    /**
     * @param  class-string<AbstractPage>  $pageClass
     */
    public function __construct(
        private string $pageClass,
    ) {}

    /**
     * @param  class-string<AbstractPage>  $pageClass
     */
    public static function make(string $pageClass): self
    {
        return new self($pageClass);
    }

    public function register(): void
    {
        app(PageRegistry::class)->register(app($this->pageClass));
    }
}
