<?php

namespace App\Plugins\Runtime\Github;

use App\Exceptions\RemoteInstallDisabledException;
use App\Models\Plugin;

final readonly class InstallGithubPlugin
{
    public function handle(string $url, ?Plugin $plugin = null): Plugin
    {
        throw new RemoteInstallDisabledException;
    }
}
