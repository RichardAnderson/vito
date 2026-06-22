<?php

namespace App\Data;

use App\Enums\WorkflowRunStatus;
use App\Models\WorkflowRun;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(WorkflowRun::class)]
#[ExposesMethods([
    'getLogContent' => 'string',
])]
final class WorkflowRunData extends Data
{
    public function __construct(
        public int $id,
        public ?int $workflow_id,
        public ?int $user_id,
        public ?string $current_node_id,
        public ?string $current_node_label,
        public WorkflowRunStatus $status,
    ) {}
}
