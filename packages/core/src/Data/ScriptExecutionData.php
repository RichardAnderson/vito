<?php

namespace App\Data;

use App\Enums\ScriptExecutionStatus;
use App\Models\ScriptExecution;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(ScriptExecution::class)]
#[ExposesMethods([
    'getContent' => 'string',
])]
final class ScriptExecutionData extends Data
{
    public function __construct(
        public int $id,
        public int $script_id,
        public ?int $server_id,
        public int $server_log_id,
        public string $user,
        public ScriptExecutionStatus $status,
        public ?ServerData $server,
    ) {}
}
