<?php

namespace App\Data;

use App\Models\Command;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Command::class)]
#[ExposesMethods([])]
final class CommandData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public string $name,
        public string $command,
        public ?SiteData $site,
        #[DataCollectionOf(CommandExecutionData::class)]
        public array $executions,
    ) {}
}
