<?php

namespace App\Pages\Components;

use App\Pages\Contracts\SchemaNode;
use App\Pages\Schema\EvaluationContext;

/**
 * Root layout container for a page's component tree. Carries optional header actions
 * rendered on the same line as the title/description.
 */
final class Page extends AbstractComponent
{
    private ?string $title = null;

    private ?string $description = null;

    /**
     * @var array<int, SchemaNode>
     */
    private array $headerActions = [];

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
        return 'page';
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

    /**
     * Primary page actions (buttons or controls), rendered on the same line as the
     * title/description.
     *
     * @param  array<int, SchemaNode>  $nodes
     */
    public function headerActions(array $nodes): self
    {
        $this->headerActions = $nodes;

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
        array_push($this->nodes, ...$nodes);

        return $this;
    }

    public function children(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<int, \App\Pages\PageAction>
     */
    public function actions(): array
    {
        $actions = [];
        foreach ($this->headerActions as $node) {
            if ($node instanceof AbstractComponent) {
                $actions = array_merge($actions, $node->actions());
            }
        }

        return $actions;
    }

    /**
     * @return array<int, \App\Pages\DataEndpoint>
     */
    public function data(): array
    {
        $data = [];
        foreach ($this->headerActions as $node) {
            if ($node instanceof AbstractComponent) {
                $data = array_merge($data, $node->data());
            }
        }

        return $data;
    }

    protected function props(EvaluationContext $ctx): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'actions' => array_values(array_filter(array_map(fn (SchemaNode $n): ?array => $n->serialize($ctx), $this->headerActions))),
        ];
    }
}
