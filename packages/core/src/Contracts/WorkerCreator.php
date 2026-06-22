<?php

namespace App\Contracts;

use App\Models\Server;
use App\Models\Site;
use App\Models\Worker;

/**
 * Core binding-seam for creating a worker. Concrete: App\Actions\Worker\CreateWorker (app).
 *
 * @phpstan-type WorkerInput array<string, mixed>
 */
interface WorkerCreator
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Server $server, array $input, ?Site $site = null): Worker;
}
