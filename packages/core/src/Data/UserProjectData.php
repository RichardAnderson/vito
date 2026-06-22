<?php

namespace App\Data;

use App\Enums\UserRole;
use App\Models\UserProject;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(UserProject::class)]
#[ExposesMethods([])]
final class UserProjectData extends Data
{
    public function __construct(
        public int $id,
        public int $project_id,
        public ?int $user_id,
        public ?string $email,
        public UserRole $role,
        public ?ProjectData $project,
    ) {}
}
