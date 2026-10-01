<?php

namespace App\Actions\Component;

use App\Models\Component;
use App\Models\Site;

/**
 * Deleting a Component must never remove an FPM pool or an OS user a Site still needs, so
 * these checks span both tables. `Site::fpmPoolSharedWithSiblings()` queries only `sites`.
 */
class ComponentResourceSharing
{
    public function fpmPoolIsShared(Component $component): bool
    {
        if ($component->isolated_user_id === null || $component->php_version === null) {
            return false;
        }

        $sharedWithSite = Site::query()
            ->where('isolated_user_id', $component->isolated_user_id)
            ->where('php_version', $component->php_version)
            ->exists();

        if ($sharedWithSite) {
            return true;
        }

        return Component::query()
            ->where('isolated_user_id', $component->isolated_user_id)
            ->where('php_version', $component->php_version)
            ->whereKeyNot($component->id)
            ->exists();
    }

    public function userIsShared(Component $component): bool
    {
        if ($component->isolated_user_id === null) {
            return false;
        }

        $sharedWithSite = Site::query()
            ->where('isolated_user_id', $component->isolated_user_id)
            ->exists();

        if ($sharedWithSite) {
            return true;
        }

        return Component::query()
            ->where('isolated_user_id', $component->isolated_user_id)
            ->whereKeyNot($component->id)
            ->exists();
    }
}
