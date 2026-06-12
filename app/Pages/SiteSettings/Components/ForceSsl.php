<?php

namespace App\Pages\SiteSettings\Components;

use App\Models\Site;
use App\Pages\PageAction;
use Illuminate\Validation\ValidationException;

/**
 * Force-SSL toggle. No row of its own — it is driven from the hosted-domains UI — so
 * both directions are exposed as headless actions.
 */
final class ForceSsl extends Section
{
    public function headless(): array
    {
        return [
            $this->action('enable-force-ssl', true, 'Force SSL enabled successfully.'),
            $this->action('disable-force-ssl', false, 'Force SSL disabled successfully.'),
        ];
    }

    private function action(string $id, bool $enabled, string $message): PageAction
    {
        return PageAction::make($id)->post()
            ->run(function (Site $site) use ($enabled, $message) {
                if (! $site->webserver()->canConfigureSSL()) {
                    throw ValidationException::withMessages(['force_ssl' => 'Force SSL cannot be changed for this webserver.']);
                }

                $site->force_ssl = $enabled;
                $site->save();
                $site->webserver()->updateVHost($site);

                return back()->with('success', $message);
            });
    }
}
