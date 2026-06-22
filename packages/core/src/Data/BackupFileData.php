<?php

namespace App\Data;

use App\Enums\BackupFileStatus;
use App\Models\BackupFile;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(BackupFile::class)]
#[ExposesMethods([
    'isAvailable' => 'bool',
    'isLocal' => 'bool',
    'path' => 'string',
])]
final class BackupFileData extends Data
{
    public function __construct(
        public int $id,
        public int $backup_id,
        public string $name,
        public ?int $size,
        public BackupFileStatus $status,
        public ?string $restored_to,
    ) {}
}
