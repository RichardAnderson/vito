<?php

namespace App\Observers;

use App\Actions\Server\BroadcastServerUpdate;
use App\Models\Metric;

final class MetricObserver
{
    public function created(Metric $metric): void
    {
        if ($metric->reboot_required === null) {
            return;
        }

        $previous = Metric::query()
            ->where('server_id', $metric->server_id)
            ->where('id', '<', $metric->id)
            ->latest('id')
            ->value('reboot_required');

        if ((bool) $previous === (bool) $metric->reboot_required) {
            return;
        }

        app(BroadcastServerUpdate::class)->broadcast($metric->server);
    }
}
