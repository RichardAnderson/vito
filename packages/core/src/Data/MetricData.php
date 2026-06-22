<?php

namespace App\Data;

use App\Models\Metric;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Metric::class)]
#[ExposesMethods([])]
final class MetricData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public ?float $load,
        public ?float $memory_total,
        public ?float $memory_used,
        public ?float $disk_total,
        public ?float $disk_used,
        public ?int $cpu_cores,
        public ?float $cpu_usage_percent,
        public ?bool $reboot_required,
        public ?ServerData $server,
    ) {}
}
