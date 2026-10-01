<?php

namespace App\Actions\Component;

use App\Enums\ComponentStatus;
use App\Models\Component;

class SyncComponentFunctions
{
    /**
     * Reconciles the component's enabled functions with the requested set, leaving untouched
     * rows alone so their status and config survive.
     *
     * @param  array<int, string>  $functions
     */
    public function sync(Component $component, array $functions): void
    {
        $requested = collect($functions)->unique()->values();

        $existing = $component->functions()->get();

        foreach ($existing as $row) {
            if (! $requested->contains($row->function)) {
                $row->delete();
            }
        }

        foreach ($requested as $function) {
            $alreadyEnabled = $existing->contains(fn ($row): bool => $row->function === $function);

            if ($alreadyEnabled) {
                continue;
            }

            $component->functions()->create([
                'function' => $function,
                'status' => ComponentStatus::CREATING,
            ]);
        }

        $component->unsetRelation('functions');
    }
}
