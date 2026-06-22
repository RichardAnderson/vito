<?php

namespace App\Data;

use App\Models\GitHook;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(GitHook::class)]
#[ExposesMethods([])]
final class GitHookData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public int $source_control_id,
        public string $hook_id,
        public ?SiteData $site,
    ) {}
}
