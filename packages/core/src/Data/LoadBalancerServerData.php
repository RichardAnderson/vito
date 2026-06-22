<?php

namespace App\Data;

use App\Models\LoadBalancerServer;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

#[HostContract(LoadBalancerServer::class)]
#[ExposesMethods([])]
final class LoadBalancerServerData extends Data
{
    public function __construct(
        public int $id,
        public int $load_balancer_id,
        public string $ip,
        public int $port,
        public int $weight,
        public bool $backup,
    ) {}
}
