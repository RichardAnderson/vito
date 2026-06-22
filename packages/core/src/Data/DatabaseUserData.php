<?php

namespace App\Data;

use App\Enums\DatabaseUserPermission;
use App\Enums\DatabaseUserStatus;
use App\Models\DatabaseUser;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(DatabaseUser::class)]
#[ExposesMethods([])]
final class DatabaseUserData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $username,
        public DatabaseUserPermission $permission,
        public string $host,
        public DatabaseUserStatus $status,
        public ?ServerData $server,
    ) {}
}
