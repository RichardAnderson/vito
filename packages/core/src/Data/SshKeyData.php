<?php

namespace App\Data;

use App\Models\SshKey;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(SshKey::class)]
#[ExposesMethods([])]
final class SshKeyData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $name,
    ) {}
}
