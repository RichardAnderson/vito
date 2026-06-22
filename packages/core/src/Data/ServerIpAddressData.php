<?php

namespace App\Data;

use App\Enums\IpAddressFamily;
use App\Enums\IpAddressStatus;
use App\Enums\IpAddressType;
use App\Models\ServerIpAddress;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(ServerIpAddress::class)]
#[ExposesMethods([])]
final class ServerIpAddressData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $ip,
        public int $prefix_length,
        public IpAddressFamily $family,
        public ?string $interface,
        public IpAddressType $type,
        public IpAddressStatus $status,
        public bool $is_managed,
        public bool $is_primary,
        public bool $is_dynamic,
        public ?ServerData $server,
    ) {}
}
