<?php

namespace App\Tables;

/**
 * Value object containing table configuration and paginated data for UI rendering.
 */
readonly class TableResult
{
    /**
     * @param  array<string, mixed>  $tableConfig
     * @param  array<string, mixed>  $paginatedData
     */
    public function __construct(
        public array $tableConfig,
        public array $paginatedData,
    ) {}

    /**
     * Get the table configuration for the UI.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->tableConfig;
    }

    /**
     * Get the paginated data for the UI.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->paginatedData;
    }
}
