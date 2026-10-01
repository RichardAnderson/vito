<?php

namespace App\Actions\Environment;

use App\Models\EnvironmentResource;

class DetachEnvironmentResource
{
    /**
     * Detaching removes the link row only — the Database or DatabaseUser is shared and survives.
     */
    public function detach(EnvironmentResource $resource): void
    {
        $resource->delete();
    }
}
