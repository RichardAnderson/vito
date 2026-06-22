<?php

namespace App\Data;

use App\Models\ServerProvider;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(ServerProvider::class)]
#[ExposesMethods([])]
final class ServerProviderData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $profile,
        public string $provider,
        public bool $connected,
        public ?int $project_id,
        public ?ProjectData $project,
    ) {}
}
