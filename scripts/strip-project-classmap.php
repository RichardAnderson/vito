<?php

/**
 * DEV WORKAROUND (composer unavailable this session): remove the project's own (App\ / Database\)
 * entries from Composer's optimized classmap so those classes resolve via PSR-4 instead — which maps
 * App\ to BOTH app/ and vendor/vito/core/src, letting files moved into packages/core resolve without a
 * `composer dump-autoload`. Vendor library classmap entries are left intact. Idempotent; a real
 * `composer dump-autoload` regenerates everything normally.
 *
 * Run after each move batch: php scripts/strip-project-classmap.php
 */

$files = [
    __DIR__.'/../vendor/composer/autoload_classmap.php',
    __DIR__.'/../vendor/composer/autoload_static.php',
];

$removed = 0;
foreach ($files as $file) {
    if (! is_file($file)) {
        continue;
    }
    $lines = file($file);
    $out = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, "'App\\\\") || str_starts_with($trimmed, "'Database\\\\")) {
            $removed++;

            continue;
        }
        $out[] = $line;
    }
    file_put_contents($file, implode('', $out));
}

echo "Stripped {$removed} project classmap entries.\n";
