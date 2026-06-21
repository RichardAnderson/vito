<?php

namespace App\Data;

use App\Models\StorageProvider;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(StorageProvider::class)]
#[ExposesMethods([])]
final class StorageProviderData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $profile,
        public string $provider,
        public ?int $project_id,
        public ?ProjectData $project,
    ) {}
}
