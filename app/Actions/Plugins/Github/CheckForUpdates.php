<?php

namespace App\Actions\Plugins\Github;

use App\Exceptions\RemoteInstallDisabledException;

final readonly class CheckForUpdates
{
    public function handle(): void
    {
        throw new RemoteInstallDisabledException;
    }
}
