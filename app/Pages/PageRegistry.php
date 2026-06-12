<?php

namespace App\Pages;

/**
 * Boot-time registry of areas and pages. Holds instances keyed by id; registration
 * is idempotent (first-wins) so re-running boot (or a plugin re-registering) is safe.
 * Pages are looked up at request time by the PageController via the route's `_page`
 * default, so a stale cached route for a disabled plugin resolves to null → 404.
 */
final class PageRegistry
{
    /**
     * @var array<string, AbstractArea>
     */
    private array $areas = [];

    /**
     * @var array<string, AbstractPage>
     */
    private array $pages = [];

    public function registerArea(AbstractArea $area): void
    {
        $this->areas[$area::id()] ??= $area;
    }

    public function register(AbstractPage $page): void
    {
        $this->pages[$page::id()] ??= $page;
    }

    public function area(string $id): ?AbstractArea
    {
        return $this->areas[$id] ?? null;
    }

    /**
     * @return array<string, AbstractArea>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    public function get(string $id): ?AbstractPage
    {
        return $this->pages[$id] ?? null;
    }

    /**
     * @return array<string, AbstractPage>
     */
    public function all(): array
    {
        return $this->pages;
    }

    public function getByRouteName(string $name): ?AbstractPage
    {
        foreach ($this->pages as $page) {
            if ($page->routeName() === $name) {
                return $page;
            }
        }

        return null;
    }
}
