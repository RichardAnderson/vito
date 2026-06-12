<?php

namespace App\Pages\SiteSettings\Components;

use App\Actions\Site\UpdateBasicAuth;
use App\Models\Site;
use App\Pages\Components\Entry;
use App\Pages\Components\Forms\Checkbox;
use App\Pages\Components\Forms\Password;
use App\Pages\Components\Forms\Repeater;
use App\Pages\Components\Forms\TextInput;
use App\Pages\PageAction;

/**
 * HTTP Basic Auth: a toggle plus a list of username/password pairs. Only available
 * on webservers that support it (nginx, caddy).
 */
final class BasicAuth extends Section
{
    public function rows(): array
    {
        return [
            Entry::make('basic-auth')->label('Basic Auth')
                ->state(fn (Site $site) => $this->label($site))
                ->visible(fn (Site $site) => in_array($site->webserver()->id(), ['nginx', 'caddy'], true))
                ->action(PageAction::make('update-basic-auth')->patch()
                    ->modalHeading('Basic Auth')
                    ->form([
                        Checkbox::make('enabled')->label('Enabled')->default(fn (Site $site) => (bool) data_get($site->type_data, 'basic_auth.enabled', false)),
                        Repeater::make('users')->schema([
                            TextInput::make('username')->label('Username'),
                            Password::make('password')->label('Password'),
                        ]),
                    ])
                    ->run(fn (Site $site, array $input) => app(UpdateBasicAuth::class)->update($site, $input))
                    ->success('Basic auth settings updated successfully.')),
        ];
    }

    private function label(Site $site): string
    {
        $enabled = (bool) data_get($site->type_data, 'basic_auth.enabled', false);
        $count = count((array) data_get($site->type_data, 'basic_auth.users', []));

        return $enabled ? "Enabled ({$count})" : 'Disabled';
    }
}
