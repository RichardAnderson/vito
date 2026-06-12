<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\DeleteSite;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\Components\Card;
use App\Pages\Components\Entry;
use App\Pages\PageAction;

/**
 * The danger-zone card: type-the-domain-to-confirm site deletion. The destroy action
 * carries its own delete-level authorize (not the page's update gate).
 */
final class DeleteSiteCard
{
    public function card(): Card
    {
        return Card::make('delete-card')
            ->title('Delete site')
            ->description('This action is irreversible and deletes all data associated with the site.')
            ->destructive()
            ->schema([
                Entry::make('delete')->label('Delete site')->state('Delete site')
                    ->action(PageAction::make('destroy')->delete()->destructive()
                        ->modalHeading(fn (Site $site) => "Delete {$site->domain}")
                        ->modalDescription('This deletes the site and all its data. This cannot be undone.')
                        ->confirmText(fn (Site $site) => $site->domain, 'domain')
                        ->authorize(fn (User $user, Site $site, Server $server): bool => $user->can('delete', [$site, $server]))
                        ->run(function (Site $site, Server $server, array $input) {
                            app(DeleteSite::class)->delete($site, $input);

                            return redirect()->route('sites', ['server' => $server])->with('success', 'Site deleted successfully.');
                        })),
            ]);
    }
}
