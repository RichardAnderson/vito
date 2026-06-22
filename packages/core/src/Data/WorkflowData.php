<?php

namespace App\Data;

use App\Models\Workflow;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Workflow::class)]
#[ExposesMethods([])]
final class WorkflowData extends Data
{
    public function __construct(
        public int $id,
        public ?int $user_id,
        public ?int $project_id,
        public string $name,
        #[DataCollectionOf(WorkflowRunData::class)]
        public array $runs,
    ) {}
}
