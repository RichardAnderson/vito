<?php

namespace App\Pages\Components;

use App\Pages\Contracts\SchemaNode;
use App\Pages\DataEndpoint;
use App\Pages\PageAction;
use App\Pages\Schema\EvaluatesClosures;
use App\Pages\Schema\EvaluationContext;
use Closure;

abstract class AbstractComponent implements SchemaNode
{
    use EvaluatesClosures;

    private bool|Closure $visible = true;

    private ?string $prefix = null;

    public function __construct(
        protected string $id,
    ) {}

    abstract public function type(): string;

    /**
     * Compose the full dotted address from the parent's prefix and this node's local
     * key, so authors write `Entry::make('php-version')` and a `Card` named
     * `details-card` yields `details-card.php-version`.
     */
    public function id(): string
    {
        return $this->prefix !== null ? "{$this->prefix}.{$this->id}" : $this->id;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return array<int, SchemaNode>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * PageActions this node contributes to the page's harvested action set.
     *
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * DataEndpoints this node contributes to the page's harvested data set.
     *
     * @return array<int, DataEndpoint>
     */
    public function data(): array
    {
        return [];
    }

    public function visible(bool|Closure $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    protected function isVisible(EvaluationContext $ctx): bool
    {
        return (bool) $this->evaluate($this->visible, $ctx);
    }

    /**
     * Component-specific wire props (everything except id/type/children).
     *
     * @return array<string, mixed>
     */
    abstract protected function props(EvaluationContext $ctx): array;

    /**
     * @return array<string, mixed>|null
     */
    public function serialize(EvaluationContext $ctx): ?array
    {
        if (! $this->isVisible($ctx)) {
            return null;
        }

        $data = array_merge([
            'id' => $this->id(),
            'type' => $this->type(),
        ], $this->props($ctx));

        $children = $this->serializeChildren($ctx);
        if ($children !== []) {
            $data['children'] = $children;
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serializeChildren(EvaluationContext $ctx): array
    {
        $children = [];

        foreach ($this->children() as $child) {
            $serialized = $child->serialize($ctx);
            if ($serialized !== null) {
                $children[] = $serialized;
            }
        }

        return $children;
    }
}
