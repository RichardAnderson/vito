<?php

namespace App\Http\Resources;

use App\Models\Component;
use App\Models\ComponentFunction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Component */
class ComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'environment_id' => $this->environment_id,
            'server_id' => $this->server_id,
            'server' => $this->whenLoaded('server', fn (): string => $this->server->name),
            'isolated_user_id' => $this->isolated_user_id,
            'user' => $this->user,
            'name' => $this->name,
            'type' => $this->type->getText(),
            'type_value' => $this->type->value,
            'type_color' => $this->type->getColor(),
            'path' => $this->path,
            'web_directory' => $this->web_directory,
            'php_version' => $this->php_version,
            'needs_redeploy' => $this->needs_redeploy,
            'functions' => $this->whenLoaded('functions', fn (): array => $this->functions
                ->map(fn (ComponentFunction $function): array => [
                    'function' => $function->function,
                    'label' => $function->label(),
                    'color' => $function->color(),
                    'status' => $function->status->getText(),
                    'status_color' => $function->status->getColor(),
                ])->all()),
            'status' => $this->status->getText(),
            'status_color' => $this->status->getColor(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
