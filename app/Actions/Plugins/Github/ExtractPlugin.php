<?php

namespace App\Actions\Plugins\Github;

use App\Exceptions\RemoteInstallDisabledException;

final readonly class ExtractPlugin
{
    public function handle(string $zipPath, string $extractPath): void
    {
        throw new RemoteInstallDisabledException;
    }
}
