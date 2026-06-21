<?php

namespace App\Data;

use App\Models\Plugin;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Plugin::class)]
#[ExposesMethods([])]
final class PluginData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $version,
        public ?string $description,
        public ?string $repo,
        public string $namespace,
        public bool $is_enabled,
        public bool $is_installed,
        public bool $updates_available,
        public string $folder,
        public string $username,
    ) {}
}
