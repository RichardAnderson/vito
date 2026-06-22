<?php

namespace App\Contracts;

use App\Models\Server;

/**
 * Core binding-seam for the server connection check. The concrete (App\Actions\Server\CheckConnection)
 * lives in the app and is bound to this contract; core models depend only on the contract so they stay
 * Action-free.
 */
interface ServerConnectionChecker
{
    public function check(Server $server, int $retry = 2): Server;
}
