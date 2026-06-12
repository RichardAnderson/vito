<?php

namespace App\Console\Commands\Pages;

use App\Pages\PageRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Inventories the framework's published, stable addresses (page ids, their route
 * names, action ids and data-endpoint ids) — the routing/API contract plugins target.
 *
 * `--check` compares the live inventory against the committed snapshot and fails on
 * drift, so removing or renaming a published address is a loud, reviewable CI diff
 * rather than silent rot.
 */
class ListPageIdsCommand extends Command
{
    protected $signature = 'pages:ids {--check : Fail if the inventory differs from the committed snapshot}';

    protected $description = 'List (or verify) the framework page address inventory';

    public function handle(PageRegistry $registry): int
    {
        $inventory = $this->buildInventory($registry);
        $json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $path = base_path('resources/pages-ids.json');

        if ($this->option('check')) {
            if (! File::exists($path)) {
                $this->error("Snapshot {$path} is missing. Run `php artisan pages:ids` and commit it.");

                return self::FAILURE;
            }

            if (File::get($path) !== $json) {
                $this->error('Page address inventory has drifted from the committed snapshot (resources/pages-ids.json).');
                $this->line('Run `php artisan pages:ids` and review the diff — renaming/removing an address is a breaking change.');

                return self::FAILURE;
            }

            $this->info('Page address inventory matches the snapshot.');

            return self::SUCCESS;
        }

        File::put($path, $json);
        $this->info("Wrote page address inventory to {$path} (".count($inventory).' pages).');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildInventory(PageRegistry $registry): array
    {
        $inventory = [];

        foreach ($registry->all() as $page) {
            $actions = array_map(fn ($action): string => $action->id(), $page->allActions());
            $data = array_map(fn ($endpoint): string => $endpoint->id(), $page->allData());
            sort($actions);
            sort($data);

            $inventory[$page::id()] = [
                'area' => $page->area()::id(),
                'route' => $page->routeName(),
                'actions' => $actions,
                'data' => $data,
            ];
        }

        ksort($inventory);

        return $inventory;
    }
}
