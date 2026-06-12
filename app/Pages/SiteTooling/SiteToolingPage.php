<?php

namespace App\Pages\SiteTooling;

use App\Actions\Site\Tooling\GetSiteTooling;
use App\Models\Site;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\Control;
use App\Pages\Components\Page;

/**
 * Site tooling (mise dev tools for the isolated user) as a framework page: the whole
 * tooling table is a `tooling-panel` control fed by GetSiteTooling. Install/uninstall
 * stay on SiteToolingController (the control calls them by name). Guarded to ready +
 * isolated sites, matching the legacy controller.
 */
final class SiteToolingPage extends AbstractPage
{
    public static function id(): string
    {
        return 'site-tooling';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'tooling';
    }

    public function routeName(): string
    {
        return 'site-tooling';
    }

    public function navTitle(): ?string
    {
        return 'Tooling';
    }

    public function guard(array $models): void
    {
        /** @var Site $site */
        $site = $models['site'];

        abort_unless($site->isReady() && $site->isIsolated(), 403);
    }

    public function schema(): array
    {
        return [
            Page::make('site-tooling')->add(
                Control::make('site-tooling.panel')->using('tooling-panel')
                    ->with(fn (Site $site): array => app(GetSiteTooling::class)->get($site)),
            ),
        ];
    }
}
