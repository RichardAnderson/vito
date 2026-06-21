<?php

namespace App\Data;

use App\Enums\HostedDomainStatus;
use App\Enums\HostedDomainType;
use App\Enums\SslMethod;
use App\Models\HostedDomain;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(HostedDomain::class)]
#[ExposesMethods([])]
final class HostedDomainData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public string $domain,
        public HostedDomainType $type,
        public HostedDomainStatus $status,
        public SslMethod $ssl_method,
        public ?int $ssl_id,
        public ?string $error,
        public ?SiteData $site,
    ) {}
}
