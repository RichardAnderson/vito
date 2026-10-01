<?php

namespace App\Http\Resources;

use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Environment */
class EnvironmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'primary_component_id' => $this->primary_component_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'branch' => $this->branch,
            'color' => $this->color,
            'auto_deploy' => $this->auto_deploy,
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'components_count' => $this->whenCounted('components'),
            'domains_count' => $this->whenCounted('domains'),
            'domains' => EnvironmentDomainResource::collection($this->whenLoaded('domains')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
