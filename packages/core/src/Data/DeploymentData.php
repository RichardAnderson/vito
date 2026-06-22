<?php

namespace App\Data;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Deployment::class)]
final class DeploymentData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public string $commit_id,
        public DeploymentStatus $status,
        public ?string $release,
        public bool $active,
        public ?SiteData $site,
    ) {}
}
