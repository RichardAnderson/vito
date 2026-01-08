<?php

namespace App\Tables;

use App\Helpers\QueryBuilder;
use App\Plugins\RegisterTableHook;
use App\Tables\Contracts\ColumnHookHandler;
use App\Tables\Contracts\DataHookHandler;
use App\Tables\Contracts\QueryHookHandler;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class AbstractTable implements Arrayable
{
    /**
     * Define the columns for this table.
     *
     * @return array<Column>
     */
    abstract public function columns(): array;

    /**
     * Define the base query for this table.
     */
    abstract public function query(): Builder|Relation;

    /**
     * Render the table, returning configuration and paginated data.
     */
    public function render(): TableResult
    {
        // 1. Resolve columns (including plugin columns)
        $columns = $this->columns();
        $columns = $this->executeColumnHooks($columns);

        // 2. Build base query
        $query = $this->query();

        // 3. Apply search/sort (using resolved columns for searchable/sortable fields)
        $defaultSort = $this->defaultSort();
        $query = QueryBuilder::for($query)
            ->searchableFields($this->getSearchableFields($columns))
            ->sortableFields($this->getSortableFields($columns))
            ->sortable($defaultSort['field'], $defaultSort['direction'])
            ->query();

        // 4. Execute query hooks
        $query = $this->executeQueryHooks($query);

        // 5. Paginate & execute
        $paginator = $query->simplePaginate($this->perPage(), pageName: $this->pageName());

        // 6. Execute data hooks
        /** @var Collection<int, array<string, mixed>> $data */
        $data = collect($paginator->items())->map(fn ($row) => $row->toArray());
        $data = $this->executeDataHooks($data);

        // 7. Filter data to only fields used by columns
        $data = $this->filterDataToColumns($data, $columns);

        // 8. Return TableResult
        return new TableResult(
            tableConfig: $this->columnsToConfig($columns),
            paginatedData: $this->buildPaginatedResponse($paginator, $data),
        );
    }

    /**
     * Get fields that are searchable (used by QueryBuilder).
     *
     * @return array<string>
     */
    public function searchableFields(): array
    {
        return $this->getSearchableFields($this->columns());
    }

    /**
     * Get searchable fields from a resolved columns array.
     * Uses the searchField if specified, otherwise falls back to accessor.
     *
     * @param  array<Column>  $columns
     * @return array<string>
     */
    protected function getSearchableFields(array $columns): array
    {
        return collect($columns)
            ->filter(fn (Column $column) => $column->searchable)
            ->map(fn (Column $column) => $column->getSearchField())
            ->values()
            ->all();
    }

    /**
     * Get sortable fields mapping from a resolved columns array.
     * Returns a mapping of accessor => sortField for all sortable columns.
     *
     * @param  array<Column>  $columns
     * @return array<string, string>
     */
    protected function getSortableFields(array $columns): array
    {
        return collect($columns)
            ->filter(fn (Column $column) => $column->sortable)
            ->mapWithKeys(fn (Column $column) => [$column->accessor => $column->getSortField()])
            ->all();
    }

    /**
     * Get the default sort configuration.
     *
     * @return array{field: string|null, direction: string}
     */
    public function defaultSort(): array
    {
        return [
            'field' => null,
            'direction' => 'asc',
        ];
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): int
    {
        return config('web.pagination_size', 15);
    }

    /**
     * Get the pagination query parameter name.
     */
    public function pageName(): string
    {
        return 'page';
    }

    /**
     * Filter data to only include fields used by columns.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  array<Column>  $columns
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterDataToColumns(Collection $data, array $columns): Collection
    {
        $allowedFields = $this->extractAllowedFields($columns);

        return $data->map(fn (array $row) => array_intersect_key($row, array_flip($allowedFields)));
    }

    /**
     * Extract all allowed fields from columns.
     *
     * @param  array<Column>  $columns
     * @return array<string>
     */
    protected function extractAllowedFields(array $columns): array
    {
        $fields = collect($columns)
            ->flatMap(fn (Column $col) => $col->fields)
            ->unique()
            ->values()
            ->all();

        // Always include id
        if (! in_array('id', $fields)) {
            array_unshift($fields, 'id');
        }

        return $fields;
    }

    /**
     * Convert columns to configuration array.
     *
     * @param  array<Column>  $columns
     * @return array<string, mixed>
     */
    protected function columnsToConfig(array $columns): array
    {
        return [
            'columns' => collect($columns)->map(fn (Column $c) => $c->toArray())->all(),
            'defaultSort' => $this->defaultSort(),
        ];
    }

    /**
     * Build the paginated response array.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    protected function buildPaginatedResponse(Paginator $paginator, Collection $data): array
    {
        return [
            'data' => $data->values()->all(),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Execute all registered query hooks for this table.
     */
    protected function executeQueryHooks(Builder|Relation $query): Builder|Relation
    {
        foreach (RegisterTableHook::getHooksFor(static::class) as $hook) {
            if ($hook['queryHook'] !== null) {
                try {
                    $query = $this->executeHookWithInterface(
                        $hook['queryHook'],
                        $query,
                        QueryHookHandler::class
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $query;
    }

    /**
     * Execute all registered data hooks for this table.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    protected function executeDataHooks(Collection $data): Collection
    {
        foreach (RegisterTableHook::getHooksFor(static::class) as $hook) {
            if ($hook['dataHook'] !== null) {
                try {
                    $data = $this->executeHookWithInterface(
                        $hook['dataHook'],
                        $data,
                        DataHookHandler::class
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $data;
    }

    /**
     * Execute all registered column hooks for this table.
     *
     * @param  array<Column>  $columns
     * @return array<Column>
     */
    protected function executeColumnHooks(array $columns): array
    {
        foreach (RegisterTableHook::getHooksFor(static::class) as $hook) {
            if ($hook['columnHook'] !== null) {
                try {
                    $columns = $this->executeHookWithInterface(
                        $hook['columnHook'],
                        $columns,
                        ColumnHookHandler::class
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $columns;
    }

    /**
     * Execute a single hook handler with interface validation.
     *
     * @param  mixed  $value  The value to pass to the handler (query, data, or columns)
     * @param  class-string  $interface  The interface the handler must implement
     * @return mixed The modified value
     */
    protected function executeHookWithInterface(mixed $handler, mixed $value, string $interface): mixed
    {
        if (is_callable($handler)) {
            return $handler($value, $this);
        }

        if (is_string($handler) && class_exists($handler)) {
            $instance = app($handler);

            if (! $instance instanceof $interface) {
                throw new InvalidArgumentException(
                    "Hook handler {$handler} must implement {$interface}"
                );
            }

            return $instance->handle($value, $this);
        }

        throw new InvalidArgumentException('Invalid hook handler: must be a callable or class string');
    }

    /**
     * Get the table configuration as an array for Inertia props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => collect($this->columns())
                ->map(fn (Column $column) => $column->toArray())
                ->all(),
            'defaultSort' => $this->defaultSort(),
        ];
    }
}
