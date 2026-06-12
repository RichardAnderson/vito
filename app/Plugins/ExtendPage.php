<?php

namespace App\Plugins;

use App\Pages\ExtensionRegistry;
use Closure;

/**
 * Plugin SDK hook: one mechanism to extend any page — framework or hard-coded alike.
 * Extenders are declarative ops (add/addRow/replace/remove + placement) whose
 * component factories receive the resolved models at render time. Addresses are
 * page-relative. Plugins never receive the raw schema tree.
 */
class ExtendPage
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $ops = [];

    public function __construct(
        private string $pageId,
    ) {}

    public static function make(string $pageId): self
    {
        return new self($pageId);
    }

    /**
     * Add a top-level node to the page, optionally positioned relative to a sibling.
     *
     * @param  Closure(array<string, \Illuminate\Database\Eloquent\Model>): \App\Pages\Contracts\SchemaNode  $factory
     */
    public function add(Closure $factory, ?string $after = null, ?string $before = null): self
    {
        $this->ops[] = ['type' => 'add-root', 'factory' => $factory, 'after' => $after, 'before' => $before];

        return $this;
    }

    /**
     * Add a node into a target container's children (e.g. a row into a card).
     *
     * @param  Closure(array<string, \Illuminate\Database\Eloquent\Model>): \App\Pages\Contracts\SchemaNode  $factory
     */
    public function addRow(string $target, Closure $factory, ?string $after = null, ?string $before = null): self
    {
        $this->ops[] = ['type' => 'add-child', 'target' => $target, 'factory' => $factory, 'after' => $after, 'before' => $before];

        return $this;
    }

    /**
     * Replace an existing node by id (allowed on framework pages; logged for support).
     *
     * @param  Closure(array<string, \Illuminate\Database\Eloquent\Model>): \App\Pages\Contracts\SchemaNode  $factory
     */
    public function replace(string $target, Closure $factory): self
    {
        $this->ops[] = ['type' => 'replace', 'target' => $target, 'factory' => $factory];

        return $this;
    }

    public function remove(string $target): self
    {
        $this->ops[] = ['type' => 'remove', 'target' => $target];

        return $this;
    }

    public function register(): void
    {
        app(ExtensionRegistry::class)->add($this->pageId, $this->ops);
    }
}
