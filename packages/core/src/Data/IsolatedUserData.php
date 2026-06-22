<?php

namespace App\Data;

use App\Models\IsolatedUser;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(IsolatedUser::class)]
#[ExposesMethods([])]
final class IsolatedUserData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $username,
        public ?ServerData $server,
    ) {}
}
