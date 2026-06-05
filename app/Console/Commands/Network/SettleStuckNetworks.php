<?php

namespace App\Console\Commands\Network;

use App\Enums\PrivateNetworkStatus;
use App\Models\PrivateNetwork;
use Illuminate\Console\Command;

class SettleStuckNetworks extends Command
{
    protected $signature = 'networks:settle-stuck';

    protected $description = 'Reset private networks stranded in the updating state past the job retry window';

    public function handle(): void
    {
        PrivateNetwork::query()
            ->where('status', PrivateNetworkStatus::UPDATING)
            ->where('updated_at', '<', now()->subHour())
            ->chunkById(100, function ($networks): void {
                /** @var PrivateNetwork $network */
                foreach ($networks as $network) {
                    $network->update(['status' => PrivateNetworkStatus::READY]);
                }
            });
    }
}
