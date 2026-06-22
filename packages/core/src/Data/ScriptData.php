<?php

namespace App\Data;

use App\Models\Script;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Script::class)]
#[ExposesMethods([])]
final class ScriptData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public ?int $project_id,
        public string $name,
        public string $content,
        #[DataCollectionOf(ScriptExecutionData::class)]
        public array $executions,
    ) {}
}
