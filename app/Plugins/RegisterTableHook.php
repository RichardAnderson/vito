<?php

namespace App\Plugins;

use App\Tables\AbstractTable;
use App\Tables\Contracts\ColumnHookHandler;
use App\Tables\Contracts\DataHookHandler;
use App\Tables\Contracts\QueryHookHandler;
use InvalidArgumentException;

class RegisterTableHook
{
    private const CONFIG_KEY = 'plugins.table_hooks';

    /**
     * @param  class-string<AbstractTable>  $tableClass
     * @param  string|callable|null  $queryHook
     * @param  string|callable|null  $dataHook
     * @param  string|callable|null  $columnHook
     */
    public function __construct(
        private string $tableClass,
        private mixed $queryHook = null,
        private mixed $dataHook = null,
        private mixed $columnHook = null,
        private int $priority = 100,
    ) {}

    /**
     * Create a new table hook registration.
     *
     * @throws InvalidArgumentException If the table class does not extend AbstractTable
     */
    public static function make(string $tableClass): self
    {
        if (! class_exists($tableClass)) {
            throw new InvalidArgumentException(
                "Table class {$tableClass} does not exist"
            );
        }

        if (! is_subclass_of($tableClass, AbstractTable::class)) {
            throw new InvalidArgumentException(
                "Table class {$tableClass} must extend ".AbstractTable::class
            );
        }

        return new self($tableClass);
    }

    /**
     * Set a query hook handler.
     * Handler receives: (Builder|Relation $query, AbstractTable $table): Builder|Relation
     *
     * @param  string|callable  $handler  Class string or callable
     *
     * @throws InvalidArgumentException If the handler class does not implement QueryHookHandler
     */
    public function queryHook(string|callable $handler): self
    {
        $this->validateHandler($handler, QueryHookHandler::class, 'query');
        $this->queryHook = $handler;

        return $this;
    }

    /**
     * Set a data hook handler.
     * Handler receives: (Collection $data, AbstractTable $table): Collection
     *
     * @param  string|callable  $handler  Class string or callable
     *
     * @throws InvalidArgumentException If the handler class does not implement DataHookHandler
     */
    public function dataHook(string|callable $handler): self
    {
        $this->validateHandler($handler, DataHookHandler::class, 'data');
        $this->dataHook = $handler;

        return $this;
    }

    /**
     * Set a column hook handler.
     * Handler receives: (array $columns, AbstractTable $table): array
     *
     * @param  string|callable  $handler  Class string or callable
     *
     * @throws InvalidArgumentException If the handler class does not implement ColumnHookHandler
     */
    public function columnHook(string|callable $handler): self
    {
        $this->validateHandler($handler, ColumnHookHandler::class, 'column');
        $this->columnHook = $handler;

        return $this;
    }

    /**
     * Validate that a handler class implements the required interface.
     *
     * @param  class-string  $interface
     *
     * @throws InvalidArgumentException
     */
    private function validateHandler(string|callable $handler, string $interface, string $hookType): void
    {
        // Callables are allowed without interface validation
        if (is_callable($handler)) {
            return;
        }

        // At this point, handler must be a non-callable string (class name)
        if (! class_exists($handler)) {
            throw new InvalidArgumentException(
                "Handler class {$handler} for {$hookType} hook does not exist"
            );
        }

        if (! is_subclass_of($handler, $interface)) {
            throw new InvalidArgumentException(
                "Handler class {$handler} for {$hookType} hook must implement {$interface}"
            );
        }
    }

    /**
     * Set priority (lower = earlier execution).
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function register(): void
    {
        $hooks = config(self::CONFIG_KEY) ?? [];

        if (! isset($hooks[$this->tableClass])) {
            $hooks[$this->tableClass] = [];
        }

        $hooks[$this->tableClass][] = [
            'queryHook' => $this->queryHook,
            'dataHook' => $this->dataHook,
            'columnHook' => $this->columnHook,
            'priority' => $this->priority,
        ];

        // Sort by priority (lower = earlier)
        usort($hooks[$this->tableClass], fn ($a, $b) => $a['priority'] <=> $b['priority']);

        config([self::CONFIG_KEY => $hooks]);
    }

    /**
     * Get all registered hooks for a table class.
     *
     * @param  class-string  $tableClass
     * @return array<int, array{queryHook: string|callable|null, dataHook: string|callable|null, columnHook: string|callable|null, priority: int}>
     */
    public static function getHooksFor(string $tableClass): array
    {
        return config(self::CONFIG_KEY.'.'.$tableClass) ?? [];
    }

    /**
     * Get all registered hooks.
     *
     * @return array<string, array<int, array{queryHook: string|callable|null, dataHook: string|callable|null, columnHook: string|callable|null, priority: int}>>
     */
    public static function get(): array
    {
        return config(self::CONFIG_KEY) ?? [];
    }
}
