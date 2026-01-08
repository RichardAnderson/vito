<?php

namespace App\Tables\Contracts;

use App\Tables\AbstractTable;
use App\Tables\Column;

interface ColumnHookHandler
{
    /**
     * Modify the columns array.
     *
     * @param  array<Column>  $columns
     * @return array<Column>
     */
    public function handle(array $columns, AbstractTable $table): array;
}
