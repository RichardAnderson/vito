<?php

namespace App\Data;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Service::class)]
final class ServiceData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $type,
        public string $name,
        public string $version,
        public ServiceStatus $status,
        public bool $is_default,
        public ?ServerData $server,
    ) {}
}
