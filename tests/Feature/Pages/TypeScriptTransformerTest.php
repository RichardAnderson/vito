<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class TypeScriptTransformerTest extends TestCase
{
    public function test_generated_types_are_not_stale(): void
    {
        $path = resource_path('js/types/generated.d.ts');
        $before = file_get_contents($path);

        $this->artisan('typescript:transform')->assertSuccessful();

        $this->assertSame(
            $before,
            file_get_contents($path),
            'resources/js/types/generated.d.ts is stale — run `php artisan typescript:transform` and commit the result.',
        );
    }

    public function test_worker_status_enum_is_generated(): void
    {
        $contents = (string) file_get_contents(resource_path('js/types/generated.d.ts'));

        $this->assertStringContainsString('export type WorkerStatus =', $contents);
        $this->assertStringContainsString("'running'", $contents);
    }
}
