<?php

namespace App\Data;

use App\Models\DNSProvider;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(DNSProvider::class)]
#[ExposesMethods([])]
final class DNSProviderData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $name,
        public string $provider,
        public bool $connected,
        public ?int $project_id,
        public ?ProjectData $project,
    ) {}
}
