<?php

/**
 * Computes the file-level vito/core closure: starting from the seed (the classes a plugin needs to boot
 * + test), follow static `App\`/`Database\Factories\` references transitively, stopping at the app
 * stop-set. Concrete handlers resolved dynamically via config strings are NOT statically referenced, so
 * they fall outside the closure and stay in the app. Output: the move list + the cross-boundary edges.
 *
 * Run: php scripts/sdk-closure.php
 */

$root = dirname(__DIR__);

$stop = ['App\\Actions\\', 'App\\Jobs\\', 'App\\Http\\', 'App\\Console\\', 'App\\Mail\\',
    'App\\WebSocket\\', 'App\\Tables\\', 'App\\Sdk\\', 'App\\Listeners\\'];
// App\Notifications\NotificationInterface moves to core (a contract); other Notifications are app concretes.
$stopExact = []; // none
$notificationInterface = 'App\\Notifications\\NotificationInterface';

// Resolve an FQCN to a source file under app/ or database/factories.
$toFile = function (string $fqcn) use ($root): ?string {
    if (str_starts_with($fqcn, 'App\\')) {
        return $root.'/app/'.str_replace('\\', '/', substr($fqcn, 4)).'.php';
    }
    if (str_starts_with($fqcn, 'Database\\Factories\\')) {
        return $root.'/database/factories/'.str_replace('\\', '/', substr($fqcn, 19)).'.php';
    }

    return null;
};

$isStop = function (string $fqcn) use ($stop, $notificationInterface): bool {
    if ($fqcn === $notificationInterface) {
        return false; // moves to core
    }
    foreach ($stop as $prefix) {
        if (str_starts_with($fqcn, $prefix)) {
            return true;
        }
    }

    return false;
};

// Seed = core-candidate roots (the classes a plugin boots/tests against).
$seedDirs = [
    'app/Models', 'app/Enums', 'app/Contracts', 'app/Traits', 'app/DTOs', 'app/Data',
    'app/Helpers', 'app/Facades', 'app/SSH', 'app/Events', 'app/Policies', 'app/ValidationRules',
    'app/Exceptions', 'app/Plugins', 'database/factories',
];

$fqcnFromFile = function (string $file) use ($root): ?string {
    $rel = substr($file, strlen($root) + 1, -4);
    if (str_starts_with($rel, 'app/')) {
        return 'App\\'.str_replace('/', '\\', substr($rel, 4));
    }
    if (str_starts_with($rel, 'database/factories/')) {
        return 'Database\\Factories\\'.str_replace('/', '\\', substr($rel, 19));
    }

    return null;
};

$queue = [];
foreach ($seedDirs as $dir) {
    $path = $root.'/'.$dir;
    if (! is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php' && ($fqcn = $fqcnFromFile($f->getPathname()))) {
            $queue[] = $fqcn;
        }
    }
}

$closure = [];   // fqcn => file
$edges = [];     // "source -> target"
$seen = [];

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

    $code = (string) file_get_contents($file);
    // Collect referenced App\/Database\Factories symbols: use-statements + new/static/typed FQCNs.
    preg_match_all('/(?:^use\s+|new\s+\\\\?|\\\\)((?:App|Database\\\\Factories)\\\\[A-Za-z0-9_\\\\]+)/m', $code, $m);
    $refs = array_unique($m[1]);
    foreach ($refs as $ref) {
        $ref = ltrim($ref, '\\');
        if ($ref === $fqcn) {
            continue;
        }
        if ($isStop($ref)) {
            $edges[] = $fqcn.'  ->  '.$ref;

            continue;
        }
        if (! isset($seen[$ref])) {
            $queue[] = $ref;
        }
    }
}

ksort($closure);
$edges = array_values(array_unique($edges));
sort($edges);

// Summarize the closure by top-level App\ subtree.
$bySubtree = [];
foreach (array_keys($closure) as $fqcn) {
    $parts = explode('\\', $fqcn);
    $key = $parts[0].'\\'.($parts[1] ?? '');
    $bySubtree[$key] = ($bySubtree[$key] ?? 0) + 1;
}
ksort($bySubtree);

echo "CLOSURE: ".count($closure)." files\n";
foreach ($bySubtree as $k => $n) {
    echo sprintf("  %-32s %d\n", $k, $n);
}
echo "\nCROSS-BOUNDARY EDGES (core -> app stop-set): ".count($edges)."\n";
foreach ($edges as $e) {
    echo "  $e\n";
}
