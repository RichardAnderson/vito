<?php

namespace App\Http\Resources;

use App\Models\SiteDeploymentBackup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteDeploymentBackup */
class SiteDeploymentBackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'enabled' => $this->enabled,
            'folders' => $this->folders ?? [],
            'databases' => $this->databases ?? [],
            'storage_id' => $this->storage_id,
            'keep' => $this->keep,
        ];
    }
}
