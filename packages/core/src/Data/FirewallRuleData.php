<?php

namespace App\Data;

use App\Enums\FirewallRuleStatus;
use App\Models\FirewallRule;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(FirewallRule::class)]
#[ExposesMethods([])]
final class FirewallRuleData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $name,
        public string $type,
        public string $protocol,
        public string $port,
        public string $source,
        public ?string $mask,
        public string $note,
        public FirewallRuleStatus $status,
        public ?ServerData $server,
    ) {}
}
