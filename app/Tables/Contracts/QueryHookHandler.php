<?php

namespace App\Tables\Contracts;

use App\Tables\AbstractTable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

interface QueryHookHandler
{
    /**
     * Modify the query before pagination.
     */
    public function handle(Builder|Relation $query, AbstractTable $table): Builder|Relation;
}
