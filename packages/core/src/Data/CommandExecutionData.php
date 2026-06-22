<?php

namespace App\Data;

use App\Enums\CommandExecutionStatus;
use App\Models\CommandExecution;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(CommandExecution::class)]
#[ExposesMethods([
    'getContent' => 'string',
])]
final class CommandExecutionData extends Data
{
    public function __construct(
        public int $id,
        public int $command_id,
        public int $server_id,
        public int $user_id,
        public ?int $server_log_id,
        public CommandExecutionStatus $status,
        public ?ServerData $server,
    ) {}
}
