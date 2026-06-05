<?php

namespace App\Http\Resources;

use App\Models\PrivateNetworkMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrivateNetworkMember */
class PrivateNetworkMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'private_network_id' => $this->private_network_id,
            'private_network_name' => $this->whenLoaded('privateNetwork', fn () => $this->privateNetwork->name),
            'private_network_subnet' => $this->whenLoaded('privateNetwork', fn () => $this->privateNetwork->subnet),
            'server_id' => $this->server_id,
            'server_name' => $this->whenLoaded('server', fn () => $this->server->name),
            'server_ip' => $this->whenLoaded('server', fn () => $this->server->ip),
            'overlay_ip' => $this->overlay_ip,
            'interface' => $this->interface,
            'public_key' => $this->public_key,
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
