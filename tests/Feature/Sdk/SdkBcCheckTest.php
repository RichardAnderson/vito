<?php

namespace Tests\Feature\Sdk;

use App\Sdk\SdkPaths;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SdkBcCheckTest extends TestCase
{
    public function test_committed_snapshot_matches_the_current_sdk_surface(): void
    {
        $this->assertSame(
            0,
            Artisan::call('sdk:bc-check'),
            'The Plugin SDK surface drifted from the committed snapshot.'."\n".Artisan::output(),
        );
    }

    public function test_bc_check_fails_when_the_surface_drifts_from_the_snapshot(): void
    {
        $path = SdkPaths::snapshotFile();
        $original = (string) file_get_contents($path);

        try {
            $surface = json_decode($original, true);
            array_shift($surface); // drop one type → current surface now has an unsnapshotted (added) type
            file_put_contents($path, json_encode($surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            $this->assertSame(
                1,
                Artisan::call('sdk:bc-check'),
                'sdk:bc-check must fail when the surface no longer matches the snapshot.',
            );
        } finally {
            file_put_contents($path, $original);
        }

        $this->assertSame(0, Artisan::call('sdk:bc-check'), 'sdk:bc-check should pass again after the snapshot is restored.');
    }
}
