<?php

namespace App\Pages\HostedDomains;

use App\Actions\HostedDomain\ActivateHostedDomain;
use App\Actions\HostedDomain\CreateHostedDomain;
use App\Actions\HostedDomain\DeactivateHostedDomain;
use App\Actions\HostedDomain\DeleteHostedDomain;
use App\Actions\HostedDomain\ReactivateHostedDomain;
use App\Actions\HostedDomain\UpdateHostedDomain;
use App\Actions\SSL\AssignSslToDomains;
use App\Actions\SSL\GetMatchingSslCertificates;
use App\Actions\SSL\RenewSiteSsl;
use App\Enums\HostedDomainStatus;
use App\Enums\SslStatus;
use App\Enums\SslType;
use App\Jobs\HostedDomain\CheckDomainJob;
use App\Models\HostedDomain;
use App\Models\Server;
use App\Models\Site;
use App\Models\Ssl;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\Control;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicDialog;
use App\Pages\Components\DynamicTable;
use App\Pages\Components\Forms\Control as FieldControl;
use App\Pages\Components\Forms\Field;
use App\Pages\Components\Forms\Hidden;
use App\Pages\Components\Forms\Select;
use App\Pages\Components\Forms\TextInput;
use App\Pages\Components\Page;
use App\Pages\Components\RowAction;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Tables\HostedDomainTable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Site hosted domains as a framework table page, using shipped controls for the parts
 * the generic primitives can't render: a `certificate-cell` cell control, an
 * `ssl-matcher` fieldset form control (async cert lookup), and an `ssl-menu` panel
 * control (enable/disable SSL, force SSL, renew). Preserves the legacy `hosted-domains.*`
 * route names; delegates to the existing app/Actions/HostedDomain + app/Actions/SSL classes.
 */
final class HostedDomainsPage extends AbstractPage
{
    public static function id(): string
    {
        return 'hosted-domains';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'domains';
    }

    public function routeName(): string
    {
        return 'hosted-domains';
    }

    public function navTitle(): ?string
    {
        return 'Domains';
    }

    public function schema(): array
    {
        return [
            Page::make('hosted-domains')
                ->title('Domains')
                ->description('Manage domains and SSL assignments for this site')
                ->headerActions([
                    Control::make('hosted-domains.ssl-menu')->using('ssl-menu')
                        ->with(fn (Site $site): array => ['hasSiteSsl' => $this->hasSiteSsl($site)]),
                    DynamicButton::make('hosted-domains.add-button')
                        ->label('Add Domain')
                        ->dialog(DynamicDialog::make('create-hosted-domain-dialog')
                            ->title('Add Domain')
                            ->action('store')
                            ->formFields($this->formFields())),
                ])
                ->add(
                    DynamicTable::make('hosted-domains')
                        ->table(HostedDomainTable::class)
                        ->query(fn (array $models) => $models['site']->hostedDomains())
                        ->rowActions([
                            RowAction::make('Edit')
                                ->action('update', ['hostedDomain' => ':id'])
                                ->dialog(DynamicDialog::make('edit-hosted-domain-dialog')
                                    ->title('Edit Domain')
                                    ->action('update')
                                    ->formFields($this->formFields(true))),
                            RowAction::make('Validate')
                                ->action('check-dns', ['hostedDomain' => ':id'])
                                ->visibleWhen('status_value', 'pending'),
                            RowAction::make('Force Validate')
                                ->action('force-activate', ['hostedDomain' => ':id'])
                                ->confirm('The domain is currently pending because we could not confirm that it resolves to this server. Force validating updates the server configuration regardless. Continue?')
                                ->visibleWhen('status_value', 'pending'),
                            RowAction::make('Deactivate')
                                ->action('deactivate', ['hostedDomain' => ':id'])
                                ->visibleWhen('status_value', ['active', 'pending']),
                            RowAction::make('Reactivate')
                                ->action('reactivate', ['hostedDomain' => ':id'])
                                ->visibleWhen('status_value', 'inactive'),
                            RowAction::make('Delete')
                                ->action('destroy', ['hostedDomain' => ':id'])
                                ->confirm('Are you sure you want to delete this domain?')
                                ->destructive()
                                ->visibleWhen('type_value', ['alias', 'redirect']),
                        ]),
                ),
        ];
    }

    public function headless(): array
    {
        return [
            PageAction::make('store')->post()
                ->authorize(fn (User $user, Site $site, Server $server): bool => $user->can('create', [HostedDomain::class, $site, $server]))
                ->run(function (Site $site, array $input) {
                    app(CreateHostedDomain::class)->create($site, $input);

                    return back()->with('success', 'Domain added successfully.');
                }),

            PageAction::make('update')->put()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('update', [$hostedDomain, $site, $server]))
                ->run(function (Site $site, HostedDomain $hostedDomain, array $input) {
                    app(UpdateHostedDomain::class)->update($hostedDomain, $site, $input);

                    return back()->with('success', 'Domain updated successfully.');
                }),

            PageAction::make('destroy')->delete()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('delete', [$hostedDomain, $site, $server]))
                ->run(function (HostedDomain $hostedDomain) {
                    app(DeleteHostedDomain::class)->delete($hostedDomain);

                    return back()->with('success', 'Domain deleted successfully.');
                }),

            PageAction::make('force-activate')->post()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('update', [$hostedDomain, $site, $server]))
                ->run(function (HostedDomain $hostedDomain) {
                    app(ActivateHostedDomain::class)->activate($hostedDomain);

                    return back()->with('success', 'Domain has been force validated.');
                }),

            PageAction::make('deactivate')->post()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('update', [$hostedDomain, $site, $server]))
                ->run(function (HostedDomain $hostedDomain) {
                    app(DeactivateHostedDomain::class)->deactivate($hostedDomain);

                    return back()->with('success', 'Domain has been deactivated.');
                }),

            PageAction::make('reactivate')->post()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('update', [$hostedDomain, $site, $server]))
                ->run(function (HostedDomain $hostedDomain) {
                    app(ReactivateHostedDomain::class)->reactivate($hostedDomain);

                    return back()->with('info', 'Reactivating domain.');
                }),

            PageAction::make('check-dns')->post()
                ->bind('hostedDomain', HostedDomain::class, 'site')
                ->authorize(fn (User $user, HostedDomain $hostedDomain, Site $site, Server $server): bool => $user->can('update', [$hostedDomain, $site, $server]))
                ->run(function (HostedDomain $hostedDomain) {
                    $hostedDomain->status = HostedDomainStatus::UPDATING;
                    $hostedDomain->save();

                    dispatch(new CheckDomainJob($hostedDomain))->onQueue('ssh');

                    return back()->with('info', 'Validating domain.');
                }),

            PageAction::make('renew-ssl')->post()
                ->authorize(fn (User $user, Site $site, Server $server): bool => $user->can('create', [HostedDomain::class, $site, $server]))
                ->run(function (Site $site) {
                    try {
                        app(RenewSiteSsl::class)->renew($site);
                    } catch (ValidationException $e) {
                        return back()->with('error', $e->getMessage());
                    }

                    return back()->with('info', 'Renewing site SSL certificate.');
                }),

            DataEndpoint::make('matching-ssls')
                ->authorize(fn (User $user, Site $site, Server $server): bool => $user->can('viewAny', [HostedDomain::class, $site, $server]))
                ->resolve(function (Site $site, Request $request): array {
                    $domain = (string) $request->query('domain', '');

                    if ($domain === '' || ! preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain)) {
                        return ['certificates' => [], 'best_match_id' => null];
                    }

                    $certificates = app(GetMatchingSslCertificates::class)->forDomain($site, $domain);
                    $serverSsls = Ssl::activeServerLevel($site->server_id)->get();
                    $bestMatch = app(AssignSslToDomains::class)->findBestMatch($domain, $serverSsls);

                    return [
                        'certificates' => $certificates,
                        'best_match_id' => $bestMatch?->id,
                    ];
                }),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private function formFields(bool $edit = false): array
    {
        $fields = [
            TextInput::make('domain')->label('Domain')->placeholder('example.com'),
            Select::make('type')->label('Type')->options(['alias' => 'Alias', 'redirect' => 'Redirect'])->default('alias'),
            FieldControl::make('ssl')->using('ssl-matcher'),
        ];

        if ($edit) {
            array_unshift($fields, Hidden::make('hostedDomain'), Hidden::make('ssl_method'), Hidden::make('ssl_id'));
        }

        return $fields;
    }

    private function hasSiteSsl(Site $site): bool
    {
        return $site->ssls()
            ->where('type', SslType::LETSENCRYPT)
            ->where('status', SslStatus::CREATED)
            ->exists();
    }
}
