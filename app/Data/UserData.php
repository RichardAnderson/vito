<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(User::class)]
#[ExposesMethods([
    'isAdmin' => 'bool',
])]
final class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $timezone,
        public bool $is_admin,
        public ?int $current_project_id,
    ) {}
}
