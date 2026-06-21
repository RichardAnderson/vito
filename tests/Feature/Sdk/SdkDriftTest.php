<?php

namespace Tests\Feature\Sdk;

use App\Sdk\SdkPaths;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SdkDriftTest extends TestCase
{
    public function test_committed_sdk_artifacts_match_the_projections(): void
    {
        $exitCode = Artisan::call('sdk:check');

        $this->assertSame(
            0,
            $exitCode,
            'Plugin SDK artifacts are out of date — run `php artisan sdk:generate` and commit the result.'."\n".Artisan::output(),
        );
    }

    public function test_sdk_check_fails_when_an_artifact_drifts(): void
    {
        $path = SdkPaths::typeScriptFile();
        $original = (string) file_get_contents($path);

        try {
            file_put_contents($path, $original.'// drift'."\n");

            $this->assertSame(
                1,
                Artisan::call('sdk:check'),
                'sdk:check must return a non-zero exit code when a committed artifact drifts.',
            );
        } finally {
            file_put_contents($path, $original);
        }

        $this->assertSame(0, Artisan::call('sdk:check'), 'sdk:check should pass again after the artifact is restored.');
    }
}
