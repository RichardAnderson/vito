<?php

namespace App\Actions\Environment;

use App\Models\EnvironmentVariable;
use Illuminate\Support\Facades\DB;

class DeleteEnvironmentVariable
{
    public function delete(EnvironmentVariable $variable): void
    {
        DB::transaction(function () use ($variable): void {
            $environment = $variable->environment;
            $componentId = $variable->component_id;

            $variable->delete();

            $environment->components()
                ->when($componentId !== null, fn ($query) => $query->whereKey($componentId))
                ->update(['needs_redeploy' => true]);
        });
    }
}
