<?php

namespace App\Plugins\Runtime\Github;

use App\Exceptions\RemoteInstallDisabledException;

final readonly class CheckForUpdates
{
    public function handle(): void
    {
        throw new RemoteInstallDisabledException;
    }
}
