<?php

/**
 * Emits the exact `git mv` set for Phase 4: every App\ class in the core closure moves from app/REL to
 * packages/core/src/REL (FQCN-preserving). Excludes app-only Traits\HandlesWorkerFailure, the
 * helpers.php files-autoload entry, and anything outside app/ (factories/migrations/config = Phase 5).
 *
 * Run: php scripts/sdk-move.php   (prints "src\tdst" lines)
 */

$root = dirname(__DIR__);
$stop = ['App\\Actions\\', 'App\\Jobs\\', 'App\\Http\\', 'App\\Console\\', 'App\\Mail\\',
    'App\\WebSocket\\', 'App\\Tables\\', 'App\\Sdk\\', 'App\\Listeners\\'];
$excludeFqcn = ['App\\Traits\\HandlesWorkerFailure'];

$isStop = function (string $f) use ($stop): bool {
    foreach ($stop as $p) {
        if (str_starts_with($f, $p)) {
            return true;
        }
    }

    return false;
};
$toFile = fn (string $f): ?string => str_starts_with($f, 'App\\')
    ? $root.'/app/'.str_replace('\\', '/', substr($f, 4)).'.php' : null;
$fromFile = function (string $file) use ($root): ?string {
    $rel = substr($file, strlen($root) + 1, -4);

    return str_starts_with($rel, 'app/') ? 'App\\'.str_replace('/', '\\', substr($rel, 4)) : null;
};

$seedDirs = ['app/Models', 'app/Enums', 'app/Contracts', 'app/Traits', 'app/DTOs', 'app/Data',
    'app/Helpers', 'app/Facades', 'app/SSH', 'app/Events', 'app/Policies', 'app/ValidationRules',
    'app/Exceptions', 'app/Plugins', 'database/factories'];

$queue = [];
foreach ($seedDirs as $dir) {
    $path = $root.'/'.$dir;
    if (! is_dir($path)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() === 'php' && ($fqcn = $fromFile($f->getPathname()))) {
            $queue[] = $fqcn;
        }
    }
}

$seen = [];
$closure = [];
while ($queue) {
    $fqcn = array_shift($queue);
    if (isset($seen[$fqcn])) {
        continue;
    }
    $seen[$fqcn] = true;
    $file = $toFile($fqcn);
    if ($file === null || ! is_file($file)) {
        continue;
    }
    $closure[$fqcn] = $file;
    preg_match_all('/(?:^use\s+|new\s+\\\\?|\\\\)(App\\\\[A-Za-z0-9_\\\\]+)/m', (string) file_get_contents($file), $m);
    foreach (array_unique($m[1]) as $ref) {
        $ref = ltrim($ref, '\\');
        if ($ref !== $fqcn && ! $isStop($ref) && ! isset($seen[$ref])) {
            $queue[] = $ref;
        }
    }
}

ksort($closure);
foreach ($closure as $fqcn => $file) {
    if (in_array($fqcn, $excludeFqcn, true)) {
        continue;
    }
    $rel = substr($file, strlen($root.'/app/'));            // e.g. Models/Site.php
    echo $file."\t".$root.'/packages/core/src/'.$rel."\n";
}
