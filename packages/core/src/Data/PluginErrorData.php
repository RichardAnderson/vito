<?php

namespace App\Data;

use App\Models\PluginError;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(PluginError::class)]
#[ExposesMethods([])]
final class PluginErrorData extends Data
{
    public function __construct(
        public int $id,
        public int $plugin_id,
        public string $error_type,
        public string $error_message,
        public ?string $stack_trace,
        public ?string $file,
        public ?int $line,
        public bool $is_fatal,
        public ?PluginData $plugin,
    ) {}
}
