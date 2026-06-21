<?php

namespace App\Data;

use App\Models\ServerTemplate;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(ServerTemplate::class)]
#[ExposesMethods([])]
final class ServerTemplateData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $name,
    ) {}
}
