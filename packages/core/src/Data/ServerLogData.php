<?php

namespace App\Data;

use App\Models\ServerLog;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(ServerLog::class)]
#[ExposesMethods([])]
final class ServerLogData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public ?int $site_id,
        public string $type,
        public string $name,
        public string $disk,
        public bool $is_remote,
        public ?ServerData $server,
        public ?SiteData $site,
    ) {}
}
