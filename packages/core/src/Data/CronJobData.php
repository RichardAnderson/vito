<?php

namespace App\Data;

use App\Enums\CronjobStatus;
use App\Models\CronJob;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(CronJob::class)]
#[ExposesMethods([
    'frequencyLabel' => 'string',
    'isEnabled' => 'bool',
    'isDisabled' => 'bool',
])]
final class CronJobData extends Data
{
    public function __construct(
        public int $id,
        public int $server_id,
        public ?int $site_id,
        public ?string $name,
        public string $command,
        public string $user,
        public string $frequency,
        public bool $hidden,
        public CronjobStatus $status,
        public ?ServerData $server,
        public ?SiteData $site,
    ) {}
}
