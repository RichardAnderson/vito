<?php

namespace App\Data;

use App\Models\File;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(File::class)]
#[ExposesMethods([
    'getFilePath' => 'string',
    'isExtractable' => 'bool',
])]
final class FileData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public int $server_id,
        public string $server_user,
        public string $path,
        public string $type,
        public string $name,
        public int $size,
        public int $links,
        public string $owner,
        public string $group,
        public string $permissions,
    ) {}
}
