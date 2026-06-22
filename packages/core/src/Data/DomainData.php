<?php

namespace App\Data;

use App\Models\Domain;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Domain::class)]
#[ExposesMethods([])]
final class DomainData extends Data
{
    public function __construct(
        public int $id,
        public int $dns_provider_id,
        public int $user_id,
        public int $project_id,
        public string $domain,
        public string $provider_domain_id,
        public ?ProjectData $project,
    ) {}
}
