<?php

namespace App\Data;

use App\Models\DNSRecord;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(DNSRecord::class)]
#[ExposesMethods([])]
final class DNSRecordData extends Data
{
    public function __construct(
        public int $id,
        public int $domain_id,
        public string $type,
        public string $name,
        public string $content,
        public int $ttl,
        public bool $proxied,
        public ?int $priority,
        public ?DomainData $domain,
    ) {}
}
