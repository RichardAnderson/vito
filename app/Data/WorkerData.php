<?php

namespace App\Data;

use App\Enums\WorkerStatus;
use App\Models\Worker;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Worker::class)]
#[ExposesMethods([
    'isSiteBootstrap' => 'bool',
])]
final class WorkerData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public int $site_id,
        public string $name,
        public string $command,
        public string $user,
        public bool $auto_start,
        public bool $auto_restart,
        public int $numprocs,
        public WorkerStatus $status,
        public ?string $error,
        public ?ServerData $server,
        public ?SiteData $site,
    ) {}
}
