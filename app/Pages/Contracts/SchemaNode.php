<?php

namespace App\Pages\Contracts;

use App\Pages\Schema\EvaluationContext;

interface SchemaNode
{
    /**
     * Stable, author-assigned, dot-separated id. This is the extension-targeting
     * address (§7.1) and the React `key` contract (§8.1) — never index-based.
     */
    public function id(): string;

    /**
     * Wire discriminator, matching the frozen DynamicField grammar (`type: 'card'`).
     */
    public function type(): string;

    /**
     * Direct child nodes, enabling generic tree walks (placement, collision
     * detection, action-URL resolution) implemented once instead of per-DTO.
     *
     * @return array<int, SchemaNode>
     */
    public function children(): array;

    /**
     * Serialized wire format for the given request context (closures evaluated,
     * hidden nodes filtered). Returns null when the node is not visible. MUST embed
     * `id` and `type`.
     *
     * @return array<string, mixed>|null
     */
    public function serialize(EvaluationContext $ctx): ?array;
}
