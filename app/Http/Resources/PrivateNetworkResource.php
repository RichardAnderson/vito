<?php

namespace App\Http\Resources;

use App\Models\PrivateNetwork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrivateNetwork */
class PrivateNetworkResource extends JsonResource
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
            'subnet' => $this->subnet,
            'mtu' => $this->mtu,
            'servers_count' => $this->whenCounted('members'),
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
