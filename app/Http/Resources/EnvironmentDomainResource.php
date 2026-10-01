<?php

namespace App\Http\Resources;

use App\Models\EnvironmentDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EnvironmentDomain */
class EnvironmentDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'environment_id' => $this->environment_id,
            'host' => $this->host,
            'url' => $this->url(),
            'type' => $this->type->getText(),
            'type_color' => $this->type->getColor(),
            'redirect_to_domain_id' => $this->redirect_to_domain_id,
            'redirect_to' => $this->whenLoaded('redirectTo', fn (): ?string => $this->redirectTo?->host),
            'ssl_method' => $this->ssl_method,
            'ssl_id' => $this->ssl_id,
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
