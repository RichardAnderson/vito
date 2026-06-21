<?php

namespace App\Data;

use App\Models\Project;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Project::class)]
final class ProjectData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
