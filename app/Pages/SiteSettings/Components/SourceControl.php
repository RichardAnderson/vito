<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\UpdateBranch;
use App\Actions\Site\UpdateSourceControl;
use App\Models\Server;
use App\Models\Site;
use App\Models\SourceControl as SourceControlModel;
use App\Pages\Components\Entry;
use App\Pages\Components\Forms\Select;
use App\Pages\Components\Forms\TextInput;
use App\Pages\PageAction;

/**
 * Source control settings: provider, repository, and branch. Only shown when the
 * site is linked to a source control.
 */
final class SourceControl extends Section
{
    public function rows(): array
    {
        return [
            Entry::make('source-control')->label('Source control')
                ->state(fn (Site $site) => $site->sourceControl?->provider ?? 'Change')
                ->visible(fn (Site $site) => $site->source_control_id !== null)
                ->action(PageAction::make('update-source-control')->patch()
                    ->modalHeading('Change source control')
                    ->form([
                        Select::make('source_control')->label('Source control')
                            ->options(fn (Server $server, Site $site) => $this->options($server, $site))
                            ->default(fn (Site $site) => (string) $site->source_control_id),
                    ])
                    ->run(fn (Site $site, array $input) => app(UpdateSourceControl::class)->update($site, $input))
                    ->success('Source control updated successfully.')),

            Entry::make('repository')->label('Repository')
                ->state(fn (Site $site) => $site->repository ?: '-')
                ->visible(fn (Site $site) => $site->source_control_id !== null),

            Entry::make('branch')->label('Branch')
                ->state(fn (Site $site) => $site->branch ?: 'Change')
                ->visible(fn (Site $site) => $site->source_control_id !== null)
                ->action(PageAction::make('update-branch')->patch()
                    ->modalHeading('Change branch')
                    ->form([TextInput::make('branch')->label('Branch')->default(fn (Site $site) => $site->branch)])
                    ->run(fn (Site $site, array $input) => app(UpdateBranch::class)->update($site, $input))
                    ->success('Branch updated successfully.')),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function options(Server $server, Site $site): array
    {
        return SourceControlModel::getByProjectId($server->project_id, $server->creator)
            ->get()
            ->mapWithKeys(function (SourceControlModel $sc): array {
                $label = ucfirst($sc->provider);
                if (! empty($sc->profile)) {
                    $label .= " ({$sc->profile})";
                }

                return [(string) $sc->id => $label];
            })
            ->all();
    }
}
