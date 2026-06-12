<?php

namespace App\Pages;

use App\Pages\Components\AbstractComponent;
use App\Pages\Contracts\SchemaNode;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Schema-driven page contract. A page declares one model-free `schema()` (all model
 * access deferred into closures); the framework serializes it per request and
 * harvests the PageActions/DataEndpoints co-located on its nodes. Behaviour with no
 * UI node lives in `headless()`. `render()` is a shim over `schema()` for the
 * controller; legacy pages may still override `render()`/`actions()`/`data()`.
 */
abstract class AbstractPage
{
    abstract public static function id(): string;

    abstract public function area(): AbstractArea;

    /**
     * Sub-URI appended to the area's route prefix, e.g. 'settings'.
     */
    abstract public function slug(): string;

    /**
     * The page's component tree. Built model-free: every model access must be a
     * closure (evaluated at serialize/dispatch time), never a direct read — there
     * are no models in scope here.
     *
     * @return array<int, SchemaNode>
     */
    public function schema(): array
    {
        return [];
    }

    public function routeName(): string
    {
        return 'pages.'.static::id();
    }

    /**
     * Optional per-page authorization beyond the area's canView, run after canView on
     * show/action/data. Throw (abort/AuthorizationException) to deny.
     *
     * @param  array<string, Model>  $models
     */
    public function guard(array $models): void {}

    /**
     * Behaviour (actions/data) with no UI node — e.g. endpoints driven from another
     * page, or an action selected dynamically by a row. Mixed PageAction|DataEndpoint.
     *
     * @return array<int, PageAction|DataEndpoint>
     */
    public function headless(): array
    {
        return [];
    }

    /**
     * Legacy page actions (pre-harvest). Prefer co-locating on schema() nodes.
     *
     * @return array<int, PageAction>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Legacy data endpoints (pre-harvest). Prefer co-locating on schema() nodes.
     *
     * @return array<int, DataEndpoint>
     */
    public function data(): array
    {
        return [];
    }

    /**
     * Default write gate inherited by any action that does not declare its own
     * authorize(). Returns `fn (User $user, …models): bool`, or null to require
     * every action to authorize explicitly. Never a read/canView gate.
     */
    public function defaultAuthorize(): ?Closure
    {
        return null;
    }

    /**
     * The page's full action set: harvested from the schema() tree, plus legacy
     * actions(), plus headless(). The defaultAuthorize() gate is injected into any
     * action lacking one. Throws on a duplicate id.
     *
     * @return array<int, PageAction>
     */
    final public function allActions(): array
    {
        $actions = $this->collectActions($this->schema());
        $actions = array_merge($actions, $this->actions());

        foreach ($this->headless() as $item) {
            if ($item instanceof PageAction) {
                $actions[] = $item;
            }
        }

        $default = $this->defaultAuthorize();
        if ($default !== null) {
            foreach ($actions as $action) {
                if (! $action->hasAuthorization()) {
                    $action->authorize($default);
                }
            }
        }

        $this->assertUniqueIds(array_map(fn (PageAction $a): string => $a->id(), $actions), 'action');

        return $actions;
    }

    /**
     * The page's full data set: harvested from the schema() tree, plus legacy data(),
     * plus headless(). Throws on a duplicate id.
     *
     * @return array<int, DataEndpoint>
     */
    final public function allData(): array
    {
        $data = $this->collectData($this->schema());
        $data = array_merge($data, $this->data());

        foreach ($this->headless() as $item) {
            if ($item instanceof DataEndpoint) {
                $data[] = $item;
            }
        }

        $this->assertUniqueIds(array_map(fn (DataEndpoint $d): string => $d->id(), $data), 'data endpoint');

        return $data;
    }

    /**
     * @param  array<int, SchemaNode>  $nodes
     * @return array<int, PageAction>
     */
    private function collectActions(array $nodes): array
    {
        $actions = [];

        foreach ($nodes as $node) {
            if ($node instanceof AbstractComponent) {
                $actions = array_merge($actions, $node->actions(), $this->collectActions($node->children()));
            }
        }

        return $actions;
    }

    /**
     * @param  array<int, SchemaNode>  $nodes
     * @return array<int, DataEndpoint>
     */
    private function collectData(array $nodes): array
    {
        $data = [];

        foreach ($nodes as $node) {
            if ($node instanceof AbstractComponent) {
                $data = array_merge($data, $node->data(), $this->collectData($node->children()));
            }
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function assertUniqueIds(array $ids, string $kind): void
    {
        $duplicates = array_keys(array_filter(array_count_values($ids), fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            throw new PageComponentException(
                "Page '".static::id()."' has duplicate {$kind} id(s): ".implode(', ', $duplicates).'.',
            );
        }
    }

    public function navTitle(): ?string
    {
        return null;
    }

    public function navIcon(): ?string
    {
        return null;
    }

    /**
     * Client-evaluated visibility for the nav entry (against area layout props).
     */
    public function navVisibleWhen(): ?string
    {
        return null;
    }
}
