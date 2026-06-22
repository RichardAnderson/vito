<?php

namespace App\Console\Commands\Sdk;

use App\Sdk\SdkPaths;
use App\Sdk\SurfaceSnapshot;
use Illuminate\Console\Command;

class SnapshotSdkCommand extends Command
{
    protected $signature = 'sdk:snapshot';

    protected $description = 'Write the committed BC snapshot of the entire published Plugin SDK surface.';

    public function handle(SurfaceSnapshot $snapshot): int
    {
        file_put_contents(SdkPaths::snapshotFile(), $snapshot->toJson());

        $this->info('Wrote the Plugin SDK contract snapshot.');

        return self::SUCCESS;
    }
}
