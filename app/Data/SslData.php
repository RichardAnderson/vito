<?php

namespace App\Data;

use App\Enums\SslStatus;
use App\Models\Ssl;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Ssl::class)]
#[ExposesMethods([])]
final class SslData extends Data
{
    public function __construct(
        public int $id,
        public ?int $site_id,
        public ?int $server_id,
        public ?int $domain_id,
        public string $type,
        public SslStatus $status,
        public bool $is_wildcard,
        public bool $has_csr,
        public string $email,
        public ?SiteData $site,
        public ?ServerData $server,
        public ?DomainData $domain,
    ) {}
}
