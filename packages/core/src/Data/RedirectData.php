<?php

namespace App\Data;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(Redirect::class)]
#[ExposesMethods([])]
final class RedirectData extends Data
{
    public function __construct(
        public int $id,
        public int $site_id,
        public string $from,
        public string $to,
        public string $mode,
        public RedirectStatus $status,
        public ?SiteData $site,
    ) {}
}
