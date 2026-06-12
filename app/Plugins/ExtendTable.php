<?php

namespace App\Plugins;

use Closure;
use Forjed\InertiaTable\Column;
use Forjed\InertiaTable\Table;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plugin SDK hook: an intention-revealing, attributed, failure-isolated facade over
 * inertia-table's beforeQuery hook. Add/remove columns, add hidden row data (for row
 * actions), and modify the query — on any table, whether it sits on a hard-coded page
 * or inside a DynamicTable.
 *
 * Registered from plugin boot(); the inertia-table HookRegistry is request-scoped, so
 * hooks re-register each request without accumulating.
 */
class ExtendTable
{
    /**
     * @var array<int, Closure>
     */
    private array $addColumns = [];

    /**
     * @var array<int, string>
     */
    private array $removeColumns = [];

    /**
     * @var array<int, array{0: string, 1: ?Closure}>
     */
    private array $rowData = [];

    /**
     * @var array<int, Closure>
     */
    private array $modifyQuery = [];

    /**
     * @param  class-string  $tableClass
     */
    public function __construct(
        private string $tableClass,
    ) {}

    /**
     * @param  class-string  $tableClass
     */
    public static function make(string $tableClass): self
    {
        return new self($tableClass);
    }

    /**
     * @param  Closure(): Column  $factory
     */
    public function addColumn(Closure $factory): self
    {
        $this->addColumns[] = $factory;

        return $this;
    }

    public function removeColumn(string $name): self
    {
        $this->removeColumns[] = $name;

        return $this;
    }

    public function addRowData(string $name, ?Closure $resolver = null): self
    {
        $this->rowData[] = [$name, $resolver];

        return $this;
    }

    /**
     * @param  Closure(mixed): void  $callback
     */
    public function modifyQuery(Closure $callback): self
    {
        $this->modifyQuery[] = $callback;

        return $this;
    }

    public function register(): void
    {
        Table::beforeQuery($this->tableClass, function ($query, array &$columns): void {
            try {
                foreach ($this->modifyQuery as $callback) {
                    $callback($query);
                }

                if ($this->removeColumns !== []) {
                    $columns = array_values(array_filter(
                        $columns,
                        fn (Column $column): bool => ! in_array($this->columnName($column), $this->removeColumns, true),
                    ));
                }

                foreach ($this->addColumns as $factory) {
                    $columns[] = $factory();
                }

                foreach ($this->rowData as [$name, $resolver]) {
                    $columns[] = $resolver !== null ? Column::data($name, $resolver) : Column::data($name);
                }
            } catch (Throwable $exception) {
                Log::warning("ExtendTable hook for {$this->tableClass} failed: {$exception->getMessage()}");
            }
        });
    }

    private function columnName(Column $column): string
    {
        return (Closure::bind(fn (): string => $this->name, $column, Column::class))();
    }
}
