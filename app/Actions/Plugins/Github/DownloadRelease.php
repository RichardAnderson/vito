<?php

namespace App\Actions\Plugins\Github;

use App\DTOs\GitHub\ReleaseDto;
use App\Exceptions\RemoteInstallDisabledException;

class DownloadRelease
{
    public function handle(ReleaseDto $release, string $location): void
    {
        throw new RemoteInstallDisabledException;
    }
}
