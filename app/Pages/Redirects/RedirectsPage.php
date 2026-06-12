<?php

namespace App\Pages\Redirects;

use App\Actions\Redirect\CreateRedirect;
use App\Actions\Redirect\DeleteRedirect;
use App\Models\Redirect;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicDialog;
use App\Pages\Components\DynamicTable;
use App\Pages\Components\Forms\Select;
use App\Pages\Components\Forms\TextInput;
use App\Pages\Components\Page;
use App\Pages\Components\RowAction;
use App\Pages\PageAction;
use App\Tables\RedirectTable;

/**
 * Site redirects as a framework table page: an inertia-table of redirects, a
 * "Create redirect" header button (form → store), and a per-row delete (bound to
 * the redirect, scoped to the site). Preserves the legacy `redirects`,
 * `redirects.store`, and `redirects.destroy` route names.
 */
final class RedirectsPage extends AbstractPage
{
    public static function id(): string
    {
        return 'redirects';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'redirects';
    }

    public function routeName(): string
    {
        return 'redirects';
    }

    public function navTitle(): ?string
    {
        return 'Redirects';
    }

    public function schema(): array
    {
        return [
            Page::make('redirects')
                ->title('Redirects')
                ->description('Redirect requests from one path to another.')
                ->headerActions([
                    DynamicButton::make('redirects.create-button')
                        ->label('Create redirect')
                        ->dialog(DynamicDialog::make('create-redirect-dialog')
                            ->title('Create Redirect')
                            ->action('store')
                            ->formFields([
                                Select::make('mode')->label('Mode')->options([
                                    '301' => '301 - Moved Permanently',
                                    '302' => '302 - Found',
                                    '307' => '307 - Temporary Redirect',
                                    '308' => '308 - Permanent Redirect',
                                    '1000' => 'Proxy (/docs to https://docs.example.com)',
                                ]),
                                TextInput::make('from')->label('From')->placeholder('/path/to/redirect/'),
                                TextInput::make('to')->label('To')->placeholder('https://new-url/'),
                            ])),
                ])
                ->add(
                    DynamicTable::make('redirects')
                        ->table(RedirectTable::class)
                        ->query(fn (array $models) => $models['site']->redirects())
                        ->rowActions([
                            RowAction::make('Delete')
                                ->action('destroy', ['redirect' => ':id'])
                                ->confirm('Are you sure you want to delete this redirect?')
                                ->destructive(),
                        ]),
                ),
        ];
    }

    public function headless(): array
    {
        return [
            PageAction::make('store')->post()
                ->authorize(fn (User $user, Site $site, Server $server): bool => $user->can('create', [Redirect::class, $site, $server]))
                ->run(function (Site $site, array $input) {
                    app(CreateRedirect::class)->create($site, $input);

                    return back()->with('info', 'Creating the redirect');
                }),

            PageAction::make('destroy')->delete()
                ->bind('redirect', Redirect::class, 'site')
                ->authorize(fn (User $user, Redirect $redirect, Site $site, Server $server): bool => $user->can('delete', [$redirect, $site, $server]))
                ->run(function (Site $site, Redirect $redirect) {
                    app(DeleteRedirect::class)->delete($site, $redirect);

                    return back()->with('info', 'Deleting the redirect');
                }),
        ];
    }
}
