<?php

namespace App\Tables\Contracts;

use App\Tables\AbstractTable;
use Illuminate\Support\Collection;

interface DataHookHandler
{
    /**
     * Transform the data collection after pagination.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Collection $data, AbstractTable $table): Collection;
}
