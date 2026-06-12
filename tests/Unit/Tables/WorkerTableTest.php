<?php

namespace Tests\Unit\Tables;

use App\Models\Worker;
use App\Tables\WorkerTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginates_workers_with_row_action_context(): void
    {
        Worker::factory()->create([
            'server_id' => $this->server->id,
            'site_id' => $this->site->id,
            'name' => 'queue-worker',
        ]);

        $data = WorkerTable::make($this->server->workers())->identifier('workers-table')->simplePaginate();

        $this->assertArrayHasKey('data', $data);
        $this->assertCount(1, $data['data']);

        $row = $data['data'][0];
        $this->assertSame('queue-worker', $row['name']);
        $this->assertSame($this->server->id, $row['server_id']);
        $this->assertArrayHasKey('status_value', $row);
        $this->assertArrayHasKey('is_site_bootstrap', $row);
    }
}
