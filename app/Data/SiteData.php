<?php

namespace App\Data;

use App\Enums\SiteStatus;
use App\Models\Site;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Site::class)]
#[ExposesMethods([
    'getUrl' => 'string',
    'isReady' => 'bool',
])]
final class SiteData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public string $domain,
        public string $web_directory,
        public string $path,
        public string $php_version,
        public string $repository,
        public string $branch,
        public SiteStatus $status,
        public int $port,
        public ?ServerData $server,
    ) {}
}
