<?php

namespace App\Data;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Backup::class)]
#[ExposesMethods([
    'isCustomInterval' => 'bool',
])]
final class BackupData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public int $storage_id,
        public ?int $database_id,
        public BackupType $type,
        public string $path,
        public string $interval,
        public int $keep_backups,
        public ?BackupStatus $status,
        public bool $enabled,
        public ?ServerData $server,
    ) {}
}
