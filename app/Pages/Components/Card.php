<?php

namespace App\Pages\Components;

use App\Pages\Contracts\SchemaNode;
use App\Pages\Schema\EvaluationContext;

/**
 * Settings card: a titled container of rows (label/value, inline edit, footer
 * buttons). Supports a destructive variant for danger zones.
 */
final class Card extends AbstractComponent
{
    private ?string $title = null;

    private ?string $description = null;

    private bool $destructive = false;

    /**
     * @var array<int, SchemaNode>
     */
    private array $nodes = [];

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function type(): string
    {
        return 'card';
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function destructive(bool $destructive = true): self
    {
        $this->destructive = $destructive;

        return $this;
    }

    /**
     * @param  array<int, SchemaNode>  $nodes
     */
    public function schema(array $nodes): self
    {
        return $this->add(...$nodes);
    }

    public function add(SchemaNode ...$nodes): self
    {
        $prefix = $this->id();

        foreach ($nodes as $node) {
            if ($node instanceof AbstractComponent && ! str_starts_with($node->id(), "{$prefix}.")) {
                $node->prefix($prefix);
            }
            $this->nodes[] = $node;
        }

        return $this;
    }

    public function children(): array
    {
        return $this->nodes;
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'destructive' => $this->destructive,
        ];
    }
}
