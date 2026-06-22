<?php

namespace App\Data;

use App\Models\DeploymentScript;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(DeploymentScript::class)]
#[ExposesMethods([
    'shouldRestartWorkers' => 'bool',
])]
final class DeploymentScriptData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public string $name,
        public string $content,
        public ?SiteData $site,
    ) {}
}
