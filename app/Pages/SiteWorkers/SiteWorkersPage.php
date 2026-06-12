<?php

namespace App\Pages\SiteWorkers;

use App\Models\Site;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\Control;
use App\Pages\Components\DynamicTable;
use App\Pages\Components\Page;
use App\Tables\WorkerTable;

/**
 * Site workers as a framework table page. The workers table data ships through the
 * framework (DynamicTable → tables:workers), but the rich, status-dependent row menu
 * and the logs/env dialogs are a `worker-actions` row-actions control + a
 * `workers-header` control, reusing the existing worker components and named routes.
 * All worker mutations (create/update/delete/start/stop/restart/env/logs/resync/
 * restart-all) stay on WorkerController (shared with the server workers page).
 */
final class SiteWorkersPage extends AbstractPage
{
    public static function id(): string
    {
        return 'site-workers';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'workers';
    }

    public function routeName(): string
    {
        return 'workers.site';
    }

    public function navTitle(): ?string
    {
        return 'Workers';
    }

    public function schema(): array
    {
        return [
            Page::make('site-workers')
                ->title('Workers')
                ->description('Manage the background workers for this site.')
                ->headerActions([
                    Control::make('site-workers.header')->using('workers-header'),
                ])
                ->add(
                    DynamicTable::make('workers')
                        ->table(WorkerTable::class)
                        ->query(fn (array $models) => $models['site']->workers())
                        ->rowActionsControl('worker-actions'),
                ),
        ];
    }
}
