<?php

namespace App\Pages\SiteSettings;

use App\Actions\Site\UpdatePHPVersion;
use App\Actions\Site\UpdateWebDirectory;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\Card;
use App\Pages\Components\Entry;
use App\Pages\Components\Forms\Select;
use App\Pages\Components\Forms\TextInput;
use App\Pages\Components\Page;
use App\Pages\PageAction;
use App\Pages\SiteSettings\Components\BasicAuth;
use App\Pages\SiteSettings\Components\DeleteSiteCard;
use App\Pages\SiteSettings\Components\ForceSsl;
use App\Pages\SiteSettings\Components\ProxiedEndpoints;
use App\Pages\SiteSettings\Components\Statistics;
use App\Pages\SiteSettings\Components\SourceControl;
use App\Pages\SiteSettings\Components\VhostEditor;
use Closure;

/**
 * Site Settings as a framework page: a small core of identity rows plus a handful of
 * self-contained sections (source control, vhost, basic auth, statistics, …), each
 * owning its rows and behaviour. Auto-derived route names reproduce the legacy
 * `site-settings.*` names; section actions inherit the page's write gate.
 */
final class SiteSettingsPage extends AbstractPage
{
    public static function id(): string
    {
        return 'site-settings';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'settings';
    }

    public function routeName(): string
    {
        return 'site-settings';
    }

    public function navTitle(): ?string
    {
        return 'Settings';
    }

    public function defaultAuthorize(): ?Closure
    {
        return fn (User $user, Site $site, Server $server): bool => $user->can('update', [$site, $server]);
    }

    public function schema(): array
    {
        $details = Card::make('details-card')->title('Site details')->description('Update site details')->schema([
            Entry::make('id')->label('ID')->copyable()->state(fn (Site $site) => (string) $site->id),
            Entry::make('domain')->label('Domain')->link(fn (Site $site) => $site->getUrl())->state(fn (Site $site) => $site->domain),
            Entry::make('type')->label('Type')->state(fn (Site $site) => $site->type),

            ...(new SourceControl)->rows(),
            ...$this->vhost()->rows(),
            ...(new BasicAuth)->rows(),
            ...(new Statistics)->rows(),

            Entry::make('web-directory')->label('Web directory')
                ->state(fn (Site $site) => $site->web_directory ?: '/')
                ->action(PageAction::make('update-web-directory')->patch()
                    ->modalHeading('Update web directory')
                    ->form([TextInput::make('web_directory')->label('Web directory')->default(fn (Site $site) => $site->web_directory)->placeholder('e.g. public (leave empty for root)')])
                    ->run(fn (Site $site, array $input) => app(UpdateWebDirectory::class)->update($site, $input))
                    ->success('Web directory updated successfully.')),

            Entry::make('path')->label('Path')->copyable()->state(fn (Site $site) => $site->path),

            Entry::make('php-version')->label('PHP version')
                ->state(fn (Site $site) => $site->php_version)
                ->visible(fn (Site $site) => (bool) $site->php_version)
                ->action(PageAction::make('update-php-version')->patch()
                    ->modalHeading('Change PHP version')
                    ->form([Select::make('version')->label('Version')->options(fn (Server $server) => $server->installedPHPVersions())->default(fn (Site $site) => $site->php_version)])
                    ->run(fn (Site $site, array $input) => app(UpdatePHPVersion::class)->update($site, $input))
                    ->success('PHP version updated successfully.')),

            Entry::make('status')->label('Status')->badge()
                ->state(fn (Site $site) => $site->status->getText())
                ->color(fn (Site $site) => $site->status->getColor()),
            Entry::make('created-at')->label('Created at')->state(fn (Site $site) => (string) $site->created_at),
        ]);

        return [
            Page::make('site-settings')
                ->title('Settings')
                ->description("Here you can manage your site's settings")
                ->add($details, (new DeleteSiteCard)->card()),
        ];
    }

    public function headless(): array
    {
        return array_merge(
            $this->vhost()->headless(),
            (new Statistics)->headless(),
            (new ForceSsl)->headless(),
            (new ProxiedEndpoints)->headless(),
        );
    }

    private function vhost(): VhostEditor
    {
        return new VhostEditor($this->defaultAuthorize());
    }
}
