<?php

namespace App\Data;

use App\Models\SourceControl;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(SourceControl::class)]
#[ExposesMethods([
    'isGithubApp' => 'bool',
])]
final class SourceControlData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $provider,
        public string $profile,
        public ?string $url,
        public ?string $external_identifier,
        public ?int $project_id,
        public ?ProjectData $project,
    ) {}
}
