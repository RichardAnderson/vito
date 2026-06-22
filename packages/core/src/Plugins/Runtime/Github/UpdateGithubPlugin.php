<?php

namespace App\Plugins\Runtime\Github;

use App\Exceptions\RemoteInstallDisabledException;
use App\Models\Plugin;

final readonly class UpdateGithubPlugin
{
    public function handle(Plugin $plugin): void
    {
        throw new RemoteInstallDisabledException;
    }
}
