<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class PageIdsCommandTest extends TestCase
{
    public function test_check_passes_against_committed_snapshot(): void
    {
        $this->artisan('pages:ids', ['--check' => true])
            ->assertSuccessful();
    }

    public function test_inventory_lists_poc_page_addresses(): void
    {
        $this->artisan('pages:ids', ['--check' => true])->run();

        $inventory = json_decode((string) file_get_contents(base_path('resources/pages-ids.json')), true);

        $this->assertArrayHasKey('server.poc', $inventory);
        $this->assertSame('pages.server.poc', $inventory['server.poc']['route']);
        $this->assertContains('ping', $inventory['server.poc']['actions']);
        $this->assertContains('info', $inventory['server.poc']['data']);
    }
}
