<?php

namespace App\Pages\SiteCronJobs;

use App\Actions\CronJob\CreateCronJob;
use App\Actions\CronJob\DeleteCronJob;
use App\Actions\CronJob\DisableCronJob;
use App\Actions\CronJob\EditCronJob;
use App\Actions\CronJob\EnableCronJob;
use App\Models\CronJob;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Areas\SiteArea;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicDialog;
use App\Pages\Components\DynamicTable;
use App\Pages\Components\Forms\Field;
use App\Pages\Components\Forms\Hidden;
use App\Pages\Components\Forms\Select;
use App\Pages\Components\Forms\TextInput;
use App\Pages\Components\Page;
use App\Pages\Components\RowAction;
use App\Pages\PageAction;
use App\Tables\CronJobTable;

/**
 * Site cron jobs as a framework table page: list + create + edit + enable/disable +
 * delete, delegating to the existing app/Actions/CronJob classes. Preserves the
 * legacy `cronjobs.site*` route names. (The server-scoped cron jobs page stays on the
 * legacy CronJobController.)
 */
final class SiteCronJobsPage extends AbstractPage
{
    public static function id(): string
    {
        return 'site-cronjobs';
    }

    public function area(): AbstractArea
    {
        return app(SiteArea::class);
    }

    public function slug(): string
    {
        return 'cronjobs';
    }

    public function routeName(): string
    {
        return 'cronjobs.site';
    }

    public function navTitle(): ?string
    {
        return 'Cron Jobs';
    }

    public function schema(): array
    {
        return [
            Page::make('cronjobs')
                ->title('Cron Jobs')
                ->description('Schedule commands to run on a recurring basis.')
                ->headerActions([
                    DynamicButton::make('cronjobs.create-button')
                        ->label('Create cron job')
                        ->dialog(DynamicDialog::make('create-cronjob-dialog')
                            ->title('Create Cron Job')
                            ->action('store')
                            ->formFields($this->formFields())),
                ])
                ->add(
                    DynamicTable::make('cronjobs')
                        ->table(CronJobTable::class)
                        ->query(fn (array $models) => $models['site']->cronJobs())
                        ->rowActions([
                            RowAction::make('Enable')
                                ->action('enable', ['cronJob' => ':id'])
                                ->visibleWhen('status_value', 'disabled'),
                            RowAction::make('Disable')
                                ->action('disable', ['cronJob' => ':id'])
                                ->visibleWhen('status_value', 'ready'),
                            RowAction::make('Edit')
                                ->action('update', ['cronJob' => ':id'])
                                ->dialog(DynamicDialog::make('edit-cronjob-dialog')
                                    ->title('Edit Cron Job')
                                    ->action('update')
                                    ->formFields($this->formFields(true))),
                            RowAction::make('Delete')
                                ->action('destroy', ['cronJob' => ':id'])
                                ->confirm('Are you sure you want to delete this cron job?')
                                ->destructive(),
                        ]),
                ),
        ];
    }

    public function headless(): array
    {
        return [
            PageAction::make('store')->post()
                ->authorize(fn (User $user, Server $server, Site $site): bool => $user->can('create', [CronJob::class, $server, $site]))
                ->run(function (Server $server, Site $site, array $input) {
                    app(CreateCronJob::class)->create($server, $input, $site);

                    return back()->with('success', 'Cron job has been created.');
                }),

            PageAction::make('update')->put()
                ->bind('cronJob', CronJob::class, 'site')
                ->authorize(fn (User $user, CronJob $cronJob, Server $server, Site $site): bool => $user->can('update', [$cronJob, $server, $site]))
                ->run(function (Server $server, Site $site, CronJob $cronJob, array $input) {
                    app(EditCronJob::class)->edit($server, $cronJob, $input, $site);

                    return back()->with('success', 'Cron job has been updated.');
                }),

            PageAction::make('enable')->post()
                ->bind('cronJob', CronJob::class, 'site')
                ->authorize(fn (User $user, CronJob $cronJob, Server $server, Site $site): bool => $user->can('update', [$cronJob, $server, $site]))
                ->run(function (Server $server, CronJob $cronJob) {
                    app(EnableCronJob::class)->enable($server, $cronJob);

                    return back()->with('success', 'Cron job has been enabled.');
                }),

            PageAction::make('disable')->post()
                ->bind('cronJob', CronJob::class, 'site')
                ->authorize(fn (User $user, CronJob $cronJob, Server $server, Site $site): bool => $user->can('update', [$cronJob, $server, $site]))
                ->run(function (Server $server, CronJob $cronJob) {
                    app(DisableCronJob::class)->disable($server, $cronJob);

                    return back()->with('success', 'Cron job has been disabled.');
                }),

            PageAction::make('destroy')->delete()
                ->bind('cronJob', CronJob::class, 'site')
                ->authorize(fn (User $user, CronJob $cronJob, Server $server, Site $site): bool => $user->can('delete', [$cronJob, $server, $site]))
                ->run(function (Server $server, CronJob $cronJob) {
                    app(DeleteCronJob::class)->delete($server, $cronJob);

                    return back()->with('success', 'Cron job has been deleted.');
                }),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private function formFields(bool $edit = false): array
    {
        $fields = [
            TextInput::make('name')->label('Name')->placeholder('Optional'),
            TextInput::make('command')->label('Command'),
            Select::make('frequency')->label('Frequency')->options(config('core.cronjob_intervals')),
            TextInput::make('custom')->label('Custom frequency (crontab)')->placeholder('* * * * *'),
            Select::make('user')->label('User')->options(fn (Site $site) => array_combine($site->getSshUsers(), $site->getSshUsers())),
        ];

        if ($edit) {
            array_unshift($fields, Hidden::make('cronJob'));
        }

        return $fields;
    }
}
