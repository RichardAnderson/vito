<?php

namespace App\Data;

use App\Enums\DatabaseStatus;
use App\Models\Database;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Database::class)]
final class DatabaseData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $name,
        public string $collation,
        public string $charset,
        public DatabaseStatus $status,
        public ?ServerData $server,
    ) {}
}
