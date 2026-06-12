<?php

namespace App\Pages;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The context a page lives in: which models the route resolves, how they scope to
 * each other, what middleware applies, who can view, and which frontend layout
 * wraps the page. Areas are core-only and fixed (there is deliberately no
 * RegisterArea hook) — plugins register pages *into* existing areas.
 */
abstract class AbstractArea
{
    abstract public static function id(): string;

    /**
     * URI prefix containing the area's route params, e.g. 'servers/{server}/sites/{site}'.
     */
    abstract public function routePrefix(): string;

    /**
     * Ordered binding chain. Root first, children after, each scoped to an earlier param.
     *
     * @return array<int, Binding>
     */
    abstract public function bindings(): array;

    /**
     * @param  array<string, Model>  $models
     */
    abstract public function canView(User $user, array $models): bool;

    /**
     * Frontend layout key this area maps to (areaLayouts registry).
     */
    abstract public function layout(): string;

    /**
     * Route middleware merged into the page's route group. The house rule is
     * middleware AND policy; areas supply the middleware half.
     *
     * @return array<int, string>
     */
    public function middleware(): array
    {
        return ['auth', 'has-project'];
    }

    /**
     * Resolve the binding chain generically, enforcing parentage at each step.
     *
     * @param  array<string, mixed>  $params  Route params (scalars or bound models)
     * @return array<string, Model>
     */
    public function resolve(array $params): array
    {
        $resolved = [];

        foreach ($this->bindings() as $binding) {
            $value = $params[$binding->param] ?? abort(404);
            $resolved[$binding->param] = $binding->resolve($value, $resolved);
        }

        return $resolved;
    }

    /**
     * Props the area's frontend layout needs (e.g. the server resource), merged
     * into every page response in this area.
     *
     * @param  array<string, Model>  $models
     * @return array<string, mixed>
     */
    public function sharedProps(array $models): array
    {
        return [];
    }
}
