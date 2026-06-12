<?php

namespace App\Pages\SiteStats;

use App\Models\Server;
use App\Models\Site;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\Control;
use App\Pages\Components\Page;

/**
 * Site statistics as a framework page: the whole GoAccess dashboard (charts, month
 * selector, refresh) is a single `stats-dashboard` panel control. The data + refresh
 * endpoints stay on SiteStatsController (the control hits them by name).
 */
final class SiteStatsPage extends AbstractPage
{
    public static function id(): string
    {
        return 'site-stats';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'stats';
    }

    public function routeName(): string
    {
        return 'site-stats';
    }

    public function navTitle(): ?string
    {
        return 'Stats';
    }

    public function schema(): array
    {
        return [
            Page::make('site-stats')->add(
                Control::make('site-stats.dashboard')->using('stats-dashboard')
                    ->with(fn (Server $server, Site $site): array => [
                        'hasStatsService' => (bool) $server->service('log_analysis'),
                        'statsEnabled' => $site->statsEnabled(),
                    ]),
            ),
        ];
    }
}
