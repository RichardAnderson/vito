<?php

namespace App\Pages;

use App\Models\Plugin;
use App\Models\PluginError;
use App\Pages\Schema\EvaluationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Collects page extenders (from ExtendPage) attributed to the contributing plugin,
 * and applies them to a page's serialized schema tree at render time.
 *
 * Failure is atomic per plugin per page: all of one plugin's ops for a page are
 * staged and committed only if every op succeeds; if any throws or hits an address
 * collision, the whole plugin's contribution to that render is dropped and logged.
 * A torn, half-rendered plugin panel is worse than an absent one. Render failures
 * never auto-disable the plugin.
 */
final class ExtensionRegistry
{
    /**
     * pageId => list of { plugin: string, ops: array<int, array<string,mixed>> }
     *
     * @var array<string, array<int, array{plugin: string, ops: array<int, array<string, mixed>>}>>
     */
    private array $extenders = [];

    private string $currentPlugin = 'core';

    public function setCurrentPlugin(string $plugin): void
    {
        $this->currentPlugin = $plugin;
    }

    public function resetCurrentPlugin(): void
    {
        $this->currentPlugin = 'core';
    }

    /**
     * @param  array<int, array<string, mixed>>  $ops
     */
    public function add(string $pageId, array $ops): void
    {
        $this->extenders[$pageId][] = [
            'plugin' => $this->currentPlugin,
            'ops' => $ops,
        ];
    }

    public function hasExtenders(string $pageId): bool
    {
        return ! empty($this->extenders[$pageId]);
    }

    /**
     * Apply all extenders for a page to its serialized schema tree.
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    public function apply(string $pageId, array $schema, EvaluationContext $ctx): array
    {
        foreach ($this->extenders[$pageId] ?? [] as $group) {
            $working = $schema;

            try {
                foreach ($group['ops'] as $op) {
                    $working = $this->applyOp($working, $op, $ctx);
                }
                $schema = $working;
            } catch (Throwable $exception) {
                $this->logFailure($group['plugin'], $pageId, $exception);
            }
        }

        return $schema;
    }

    /**
     * Collect slot contributions for a hard-coded page: add-root ops grouped by their
     * anchor (the published slot name). Same atomic-per-plugin semantics as apply().
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function collectSlots(string $pageId, EvaluationContext $ctx): array
    {
        $slots = [];

        foreach ($this->extenders[$pageId] ?? [] as $group) {
            $staged = [];

            try {
                foreach ($group['ops'] as $op) {
                    if ($op['type'] !== 'add-root') {
                        continue;
                    }
                    $anchor = $op['after'] ?? $op['before'];
                    if ($anchor === null) {
                        continue;
                    }
                    $staged[$anchor][] = $this->build($op['factory'], $ctx);
                }

                foreach ($staged as $anchor => $nodes) {
                    $slots[$anchor] = array_merge($slots[$anchor] ?? [], $nodes);
                }
            } catch (Throwable $exception) {
                $this->logFailure($group['plugin'], $pageId, $exception);
            }
        }

        return $slots;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyOp(array $schema, array $op, EvaluationContext $ctx): array
    {
        switch ($op['type']) {
            case 'add-root':
                $node = $this->build($op['factory'], $ctx);
                $this->assertNoCollision($schema, $node['id']);

                return $this->insert($schema, $node, $op['after'], $op['before']);

            case 'add-child':
                $node = $this->build($op['factory'], $ctx);
                $this->assertNoCollision($schema, $node['id']);

                return $this->insertInto($schema, $op['target'], $node, $op['after'], $op['before']);

            case 'replace':
                $node = $this->build($op['factory'], $ctx);

                return $this->replace($schema, $op['target'], $node);

            case 'remove':
                return $this->remove($schema, $op['target']);

            default:
                throw new RuntimeException("Unknown extender op type: {$op['type']}");
        }
    }

    /**
     * @param  \Closure(array<string, Model>): \App\Pages\Contracts\SchemaNode  $factory
     * @return array<string, mixed>
     */
    private function build(\Closure $factory, EvaluationContext $ctx): array
    {
        $node = $factory($ctx->models)->serialize($ctx);

        if ($node === null) {
            throw new RuntimeException('Extender factory produced a node that is not visible.');
        }

        return $node;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     */
    private function assertNoCollision(array $schema, string $id): void
    {
        if (in_array($id, $this->collectIds($schema), true)) {
            throw new RuntimeException("Extender address collision: '{$id}' already exists in the page.");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, string>
     */
    private function collectIds(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $ids[] = $node['id'];
            }
            if (! empty($node['children'])) {
                $ids = array_merge($ids, $this->collectIds($node['children']));
            }
        }

        return $ids;
    }

    /**
     * Insert a node into a flat list at a placement; append + warn if target missing.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function insert(array $nodes, array $node, ?string $after, ?string $before): array
    {
        if ($after === null && $before === null) {
            $nodes[] = $node;

            return $nodes;
        }

        $result = [];
        $inserted = false;

        foreach ($nodes as $existing) {
            if ($before !== null && ($existing['id'] ?? null) === $before) {
                $result[] = $node;
                $inserted = true;
            }
            $result[] = $existing;
            if ($after !== null && ($existing['id'] ?? null) === $after) {
                $result[] = $node;
                $inserted = true;
            }
        }

        if (! $inserted) {
            Log::warning("Extender placement target '".($after ?? $before)."' not found; appended '{$node['id']}'.");
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function insertInto(array $nodes, string $target, array $node, ?string $after, ?string $before): array
    {
        $found = false;

        $nodes = $this->mapTree($nodes, function (array $parent) use ($target, $node, $after, $before, &$found): array {
            if (($parent['id'] ?? null) === $target) {
                $found = true;
                $parent['children'] = $this->insert($parent['children'] ?? [], $node, $after, $before);
            }

            return $parent;
        });

        if (! $found) {
            Log::warning("Extender target container '{$target}' not found; appended '{$node['id']}' to page root.");
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function replace(array $nodes, string $target, array $node): array
    {
        $found = false;

        $result = $this->mapTree($nodes, function (array $current) use ($target, $node, &$found): array {
            if (($current['id'] ?? null) === $target) {
                $found = true;

                return $node;
            }

            return $current;
        });

        if (! $found) {
            Log::warning("Extender replace target '{$target}' not found; skipped.");
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function remove(array $nodes, string $target): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $target) {
                continue;
            }
            if (! empty($node['children'])) {
                $node['children'] = $this->remove($node['children'], $target);
            }
            $result[] = $node;
        }

        return $result;
    }

    /**
     * Apply a transform to every node in the tree (depth-first, children before parent
     * is not required here; we transform the node then recurse into its children).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  \Closure(array<string, mixed>): array<string, mixed>  $fn
     * @return array<int, array<string, mixed>>
     */
    private function mapTree(array $nodes, \Closure $fn): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $node = $fn($node);
            if (! empty($node['children'])) {
                $node['children'] = $this->mapTree($node['children'], $fn);
            }
            $result[] = $node;
        }

        return $result;
    }

    private function logFailure(string $plugin, string $pageId, Throwable $exception): void
    {
        Log::warning("Dropped plugin '{$plugin}' contribution to page '{$pageId}': {$exception->getMessage()}");

        $model = Plugin::query()->where('name', $plugin)->first();
        if ($model !== null) {
            PluginError::createFromException($exception, $model);
        }
    }
}
