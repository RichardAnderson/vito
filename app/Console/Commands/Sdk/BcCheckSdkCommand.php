<?php

namespace App\Console\Commands\Sdk;

use App\Sdk\SdkPaths;
use App\Sdk\SurfaceSnapshot;
use Illuminate\Console\Command;

class BcCheckSdkCommand extends Command
{
    protected $signature = 'sdk:bc-check';

    protected $description = 'Fail if the Plugin SDK surface drifts from the committed snapshot; classify breaking vs additive.';

    public function handle(SurfaceSnapshot $snapshot): int
    {
        $path = SdkPaths::snapshotFile();
        if (! is_file($path)) {
            $this->error('No contract snapshot. Run `php artisan sdk:snapshot` to establish the baseline.');

            return self::FAILURE;
        }

        $committed = json_decode((string) file_get_contents($path), true);
        $current = $snapshot->build();

        $breaking = [];
        $additive = [];

        foreach ($committed as $type => $old) {
            if (! isset($current[$type])) {
                $breaking[] = "removed type {$type}";

                continue;
            }
            $new = $current[$type];

            if ($old['kind'] !== $new['kind']) {
                $breaking[] = "{$type}: kind {$old['kind']} => {$new['kind']}";
            }
            foreach (array_diff($old['extends'], $new['extends']) as $gone) {
                $breaking[] = "{$type}: no longer extends/implements {$gone}";
            }
            $this->classifyMembers($type, 'method', $old['methods'], $new['methods'], $breaking, $additive);
            $this->classifyMembers($type, 'property', $old['properties'], $new['properties'], $breaking, $additive);

            if (($old['hook'] ?? null) != ($new['hook'] ?? null)) {
                $breaking[] = "{$type}: hook semantics changed ".json_encode($old['hook'] ?? null).' => '.json_encode($new['hook'] ?? null);
            }
        }

        foreach (array_diff_key($current, $committed) as $type => $_) {
            $additive[] = "added type {$type}";
        }

        if ($breaking === [] && $additive === []) {
            $this->info('Plugin SDK surface matches the committed snapshot.');

            return self::SUCCESS;
        }

        foreach ($breaking as $line) {
            $this->error('BREAKING (major): '.$line);
        }
        foreach ($additive as $line) {
            $this->line('additive (minor): '.$line);
        }
        $this->newLine();
        $this->error(sprintf(
            'Plugin SDK surface changed: %d breaking, %d additive. Set the version accordingly and run `php artisan sdk:snapshot`.',
            count($breaking),
            count($additive),
        ));

        return self::FAILURE;
    }

    /**
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     * @param  list<string>  $breaking
     * @param  list<string>  $additive
     */
    private function classifyMembers(string $type, string $kind, array $old, array $new, array &$breaking, array &$additive): void
    {
        foreach ($old as $name => $signature) {
            if (! isset($new[$name])) {
                $breaking[] = "{$type}: removed {$kind} {$name}";
            } elseif ($new[$name] !== $signature) {
                $breaking[] = "{$type}: {$kind} {$name} changed {$signature} => {$new[$name]}";
            }
        }
        foreach (array_diff_key($new, $old) as $name => $_) {
            $additive[] = "{$type}: added {$kind} {$name}";
        }
    }
}
