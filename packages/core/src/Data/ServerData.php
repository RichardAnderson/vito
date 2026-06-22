<?php

namespace App\Data;

use App\Enums\OperatingSystem;
use App\Enums\ServerStatus;
use App\Models\Server;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Server::class)]
#[ExposesMethods([
    'isReady' => 'bool',
    'getSshUser' => 'string',
])]
final class ServerData extends Data
{
    public function __construct(
        public int $id,
        public int $project_id,
        public string $name,
        public string $ssh_user,
        public string $ip,
        public ?string $local_ip,
        public int $port,
        public OperatingSystem $os,
        public ServerStatus $status,
        public bool $auto_update,
        public ?ProjectData $project,
    ) {}
}
