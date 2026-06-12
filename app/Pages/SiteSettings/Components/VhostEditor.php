<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\PreviewVhost;
use App\Actions\Site\UpdateVhost;
use App\Actions\Site\UpdateVhostGeneration;
use App\Actions\Site\UpdateVhostTemplate;
use App\Actions\Webserver\GenerateCaddyConfig;
use App\Actions\Webserver\GenerateNginxConfig;
use App\Models\Site;
use App\Pages\Components\DynamicButton;
use App\Pages\Components\DynamicCardRow;
use App\Pages\Components\DynamicCodeEditor;
use App\Pages\Components\DynamicDialog;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use Closure;
use Illuminate\Http\Request;

/**
 * Reusable VHost editor: the "View VHost" + "Edit Template" rows with their data/
 * actions co-located on the Monaco editors, plus the two node-less vhost actions
 * (update-vhost, update-vhost-generation) exposed as headless(). Delegates to the
 * existing app/Actions/Site classes. The write gate is supplied by the host page.
 */
final class VhostEditor extends Section
{
    public function __construct(
        private readonly Closure $authorize,
    ) {}

    public function rows(): array
    {
        return [
            DynamicCardRow::button('details-card.vhost', 'VHost', DynamicButton::make('details-card.vhost.button')
                ->label('View VHost')
                ->variant('outline')
                ->dialog(DynamicDialog::make('vhost-dialog')->title('VHost')->sheet()->editor(
                    DynamicCodeEditor::make('vhost-editor')
                        ->load($this->vhost())
                        ->language(fn (Site $site) => $site->webserver()->id())
                        ->readonly(),
                ))),

            DynamicCardRow::button('details-card.vhost-template', 'VHost Template', DynamicButton::make('details-card.vhost-template.button')
                ->label('Edit Template')
                ->variant('outline')
                ->dialog(DynamicDialog::make('vhost-template-dialog')->title('Edit webserver template')->sheet()->editor(
                    DynamicCodeEditor::make('vhost-template-editor')
                        ->load($this->vhostTemplate())
                        ->save($this->updateVhostTemplate())
                        ->preview($this->vhostPreview())
                        ->reset($this->resetVhostTemplate())
                        ->info('This is the Mustache template used to generate the vhost. Changes here persist across SSL, domain, and redirect updates.')
                        ->language(fn (Site $site) => $site->webserver()->id()),
                ))),
        ];
    }

    /**
     * @return array<int, PageAction>
     */
    public function headless(): array
    {
        return [
            PageAction::make('update-vhost')->put()
                ->run(fn (Site $site, array $input) => app(UpdateVhost::class)->update($site, $input))
                ->success('VHost updated successfully.'),

            PageAction::make('update-vhost-generation')->patch()
                ->run(fn (Site $site, array $input) => app(UpdateVhostGeneration::class)->update($site, $input))
                ->success('VHost generation setting updated successfully.'),
        ];
    }

    private function vhost(): DataEndpoint
    {
        return DataEndpoint::make('vhost')
            ->authorize($this->authorize)
            ->resolve(fn (Site $site): array => ['content' => $site->webserver()->getVHost($site)]);
    }

    private function vhostTemplate(): DataEndpoint
    {
        return DataEndpoint::make('vhost-template')
            ->authorize($this->authorize)
            ->resolve(fn (Site $site): array => [
                'content' => $site->vhost_template ?? $this->generator($site)->defaultTemplate(),
            ]);
    }

    private function vhostPreview(): DataEndpoint
    {
        return DataEndpoint::make('vhost-preview')->post()
            ->authorize($this->authorize)
            ->resolve(fn (Site $site, Request $request): array => [
                'content' => app(PreviewVhost::class)->preview($site, ['template' => $request->input('content', $request->input('template'))]),
            ]);
    }

    private function updateVhostTemplate(): PageAction
    {
        return PageAction::make('update-vhost-template')->put()
            ->run(fn (Site $site, array $input) => app(UpdateVhostTemplate::class)->update($site, ['template' => $input['content'] ?? $input['template'] ?? '']))
            ->success('VHost template updated successfully.');
    }

    private function resetVhostTemplate(): PageAction
    {
        return PageAction::make('reset-vhost-template')->post()
            ->run(function (Site $site) {
                $site->vhost_template = null;
                $site->save();
                $site->webserver()->updateVHost($site);

                return back()->with('success', 'VHost template reset to default.');
            });
    }

    private function generator(Site $site): GenerateNginxConfig|GenerateCaddyConfig
    {
        return $site->webserver()::id() === 'caddy' ? app(GenerateCaddyConfig::class) : app(GenerateNginxConfig::class);
    }
}
