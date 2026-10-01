<?php

namespace App\Http\Resources;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'source_control_id' => $this->source_control_id,
            'source_control' => $this->whenLoaded('sourceControl', fn (): ?string => $this->source_control_id !== null ? $this->sourceControl->provider : null),
            'repository' => $this->repository,
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'environments_count' => $this->whenCounted('environments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
