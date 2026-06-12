<?php

namespace Tests\Unit\Pages;

use App\Pages\Areas\ServerArea;
use App\Pages\PageRegistry;
use App\Pages\Server\ServerPocPage;
use Tests\TestCase;

class PageRegistryTest extends TestCase
{
    public function test_registration_is_idempotent_first_wins(): void
    {
        $registry = new PageRegistry;
        $first = new ServerPocPage;
        $second = new ServerPocPage;

        $registry->register($first);
        $registry->register($second);

        $this->assertCount(1, $registry->all());
        $this->assertSame($first, $registry->get('server.poc'));
    }

    public function test_resolves_page_by_route_name(): void
    {
        $registry = new PageRegistry;
        $registry->register(new ServerPocPage);

        $this->assertNotNull($registry->getByRouteName('pages.server.poc'));
        $this->assertNull($registry->getByRouteName('pages.unknown'));
    }

    public function test_unknown_page_id_returns_null(): void
    {
        $this->assertNull((new PageRegistry)->get('does-not-exist'));
    }

    public function test_areas_register_and_resolve(): void
    {
        $registry = new PageRegistry;
        $registry->registerArea(new ServerArea);

        $this->assertInstanceOf(ServerArea::class, $registry->area('server'));
        $this->assertNull($registry->area('unknown'));
    }
}
