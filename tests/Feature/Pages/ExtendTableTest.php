<?php

namespace Tests\Feature\Pages;

use App\Models\Worker;
use App\Plugins\ExtendTable;
use App\Tables\WorkerTable;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtendTableTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Table::clearHooks(WorkerTable::class);

        parent::tearDown();
    }

    public function test_extend_table_adds_columns_and_row_data(): void
    {
        Worker::factory()->create(['server_id' => $this->server->id, 'site_id' => $this->site->id]);

        ExtendTable::make(WorkerTable::class)
            ->addColumn(fn (): Column => Column::make('region', 'Region'))
            ->addRowData('deploy_url', fn (Worker $worker): string => "https://deploy/{$worker->id}")
            ->register();

        $data = WorkerTable::make($this->server->workers())->simplePaginate();

        $columnNames = array_column($data['columns'], 'name');
        $this->assertContains('region', $columnNames);
        $this->assertArrayHasKey('deploy_url', $data['data'][0]);
    }

    public function test_extend_table_modify_query_filters_rows(): void
    {
        Worker::factory()->create(['server_id' => $this->server->id, 'name' => 'keep']);
        Worker::factory()->create(['server_id' => $this->server->id, 'name' => 'drop']);

        ExtendTable::make(WorkerTable::class)
            ->modifyQuery(fn ($query) => $query->where('name', 'keep'))
            ->register();

        $data = WorkerTable::make($this->server->workers())->simplePaginate();

        $this->assertCount(1, $data['data']);
        $this->assertSame('keep', $data['data'][0]['name']);
    }
}
