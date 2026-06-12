<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\UpdateSiteStats;
use App\Models\Server;
use App\Models\Site;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicCardRow;
use App\Pages\Components\DynamicDialog;
use App\Pages\PageAction;

/**
 * GoAccess site statistics: a single enable/disable confirm row (shown only when the
 * server has the log-analysis service) plus the two toggle actions, which are kept
 * headless because the row picks between them dynamically.
 */
final class Statistics extends Section
{
    public function rows(): array
    {
        return [
            DynamicCardRow::button('statistics', 'Statistics', DynamicButton::make('details-card.statistics.button')
                ->label(fn (Site $site) => $site->statsEnabled() ? 'Enabled' : 'Disabled')
                ->variant('outline')
                ->dialog(DynamicDialog::make('stats-dialog')
                    ->title(fn (Site $site) => $site->statsEnabled() ? 'Disable statistics' : 'Enable statistics')
                    ->confirm(fn (Site $site) => $site->statsEnabled() ? 'Disable statistics and erase historical data?' : 'Enable statistics for this site?')
                    ->action(fn (Site $site) => $site->statsEnabled() ? 'disable-stats' : 'enable-stats')))
                ->visible(fn (Server $server) => $server->services['log_analysis'] ?? false),
        ];
    }

    public function headless(): array
    {
        return [
            PageAction::make('enable-stats')->post()
                ->run(function (Site $site) {
                    app(UpdateSiteStats::class)->enable($site);

                    return back()->with('success', 'Statistics enabled for this site.');
                }),
            PageAction::make('disable-stats')->post()
                ->run(function (Site $site) {
                    app(UpdateSiteStats::class)->disable($site);

                    return back()->with('success', 'Statistics disabled and historical data erased.');
                }),
        ];
    }
}
