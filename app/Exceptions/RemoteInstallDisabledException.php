<?php

namespace App\Exceptions;

use RuntimeException;

final class RemoteInstallDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Remote plugin installation is disabled for now; it will return with the plugin registry.');
    }
}
