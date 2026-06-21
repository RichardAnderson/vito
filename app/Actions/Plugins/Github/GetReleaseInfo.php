<?php

namespace App\Actions\Plugins\Github;

use App\DTOs\GitHub\ReleaseDto;
use App\Exceptions\RemoteInstallDisabledException;

class GetReleaseInfo
{
    public function handle(string $username, string $repo, string $version = 'latest'): ?ReleaseDto
    {
        throw new RemoteInstallDisabledException;
    }
}
