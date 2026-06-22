<?php

namespace App\Data;

use App\Models\NotificationChannel;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(NotificationChannel::class)]
#[ExposesMethods([])]
final class NotificationChannelData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $provider,
        public string $label,
        public bool $connected,
        public ?int $project_id,
        public ?ProjectData $project,
    ) {}
}
