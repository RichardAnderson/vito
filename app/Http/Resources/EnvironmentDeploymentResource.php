<?php

namespace App\Http\Resources;

use App\Models\Deployment;
use App\Models\EnvironmentDeployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnvironmentDeployment */
class EnvironmentDeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'environment_id' => $this->environment_id,
            'commit_id' => $this->commit_id,
            'release' => $this->release,
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'current_stage' => $this->current_stage?->getText(),
            'components' => $this->whenLoaded('deployments', fn (): array => $this->deployments
                ->map(fn (Deployment $deployment): array => [
                    'id' => $deployment->id,
                    'component_id' => $deployment->component_id,
                    'component' => $deployment->component?->name,
                    'stage' => $deployment->current_stage?->getText(),
                    'status' => $deployment->status->getText(),
                    'status_color' => $deployment->status->getColor(),
                    'release' => $deployment->release,
                    'active' => $deployment->active,
                    'log_id' => $deployment->log_id,
                ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
