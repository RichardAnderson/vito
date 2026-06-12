<?php

namespace App\Http\Controllers;

use App\Pages\AbstractArea;
use App\Pages\AbstractPage;
use App\Pages\Components\DynamicTable;
use App\Pages\Contracts\SchemaNode;
use App\Pages\DataEndpoint;
use App\Pages\ExtensionActionRegistry;
use App\Pages\ExtensionRegistry;
use App\Pages\PageAction;
use App\Pages\PageRegistry;
use App\Pages\Schema\EvaluationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Single controller for all framework pages. Every request flows through the same
 * pipeline as core: resolve page (404 if absent from the live registry) → Area
 * binding chain (404 on foreign/mismatch) → canView (403) → render/dispatch.
 * Handlers are looked up from the registry by the route's `_page` default, never
 * embedded in the (cacheable) route action.
 */
class PageController extends Controller
{
    public function __construct(
        private readonly PageRegistry $registry,
        private readonly ExtensionRegistry $extensions,
        private readonly ExtensionActionRegistry $extensionActions,
    ) {}

    public function show(Request $request): Response
    {
        $page = $this->resolvePage($request);
        $area = $page->area();
        $models = $area->resolve($request->route()->parameters());

        abort_unless($area->canView($request->user(), $models), 403);
        $page->guard($models);

        try {
            $ctx = EvaluationContext::make($models, $request->user(), $request);
            $nodes = $page->schema();
            $schema = [];
            foreach ($nodes as $node) {
                $serialized = $node->serialize($ctx);
                if ($serialized !== null) {
                    $schema[] = $serialized;
                }
            }
            $schema = $this->extensions->apply($page::id(), $schema, $ctx);
            $tableProps = $this->tableProps($nodes, $models);
        } catch (Throwable $exception) {
            report($exception);
            $schema = [];
            $tableProps = [];
        }

        return Inertia::render('dynamic/page', array_merge(
            $area->sharedProps($models),
            $tableProps,
            [
                'area' => $area::id(),
                'layout' => $area->layout(),
                'page' => [
                    'id' => $page::id(),
                    'title' => $page->navTitle(),
                ],
                'schema' => $schema,
                'actions' => $this->actionMap($page, $models),
                'data' => $this->dataMap($page, $models),
            ],
        ));
    }

    public function action(Request $request): RedirectResponse
    {
        $page = $this->resolvePage($request);
        $area = $page->area();
        $models = $area->resolve($request->route()->parameters());

        abort_unless($area->canView($request->user(), $models), 403);
        $page->guard($models);

        $action = $this->resolveAction($page, $request);
        $models = $this->applyBinds($action->getBinds(), $models, $request);

        abort_unless($action->isAuthorized($request->user(), $models), 403);

        $input = $request->all();
        if (($form = $action->getForm()) !== null) {
            $rules = $form->validationRules();
            if ($rules !== []) {
                $input = array_merge($input, $request->validate($rules));
            }
        }

        $result = $action->dispatch($models, $input, $request);

        return $result instanceof RedirectResponse ? $result : back();
    }

    public function data(Request $request): JsonResponse
    {
        $page = $this->resolvePage($request);
        $area = $page->area();
        $models = $area->resolve($request->route()->parameters());

        $endpoint = $this->resolveEndpoint($page, $request);
        $models = $this->applyBinds($endpoint->getBinds(), $models, $request);

        if ($endpoint->inheritsCanView()) {
            abort_unless($area->canView($request->user(), $models), 403);
        } else {
            abort_unless($endpoint->isAuthorized($request->user(), $models), 403);
        }

        $page->guard($models);

        return response()->json($endpoint->run($models, $request));
    }

    /**
     * Collect DynamicTable nodes and expose each table's data as a partial-reload
     * resolvable sibling prop `tables:{id}` (closure → only evaluated when the
     * request asks for it, so realtime/sort reloads don't rebuild the page schema).
     *
     * @param  array<int, SchemaNode>  $nodes
     * @param  array<string, Model>  $models
     * @return array<string, \Closure>
     */
    private function tableProps(array $nodes, array $models): array
    {
        $props = [];

        foreach ($this->flatten($nodes) as $node) {
            if ($node instanceof DynamicTable) {
                $props["tables:{$node->id()}"] = fn (): array => $node->buildData($models);
            }
        }

        return $props;
    }

    /**
     * @param  array<int, SchemaNode>  $nodes
     * @return array<int, SchemaNode>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = $node;
            $flat = array_merge($flat, $this->flatten($node->children()));
        }

        return $flat;
    }

    public function extensionAction(Request $request): RedirectResponse
    {
        $plugin = $request->route()->defaults['_ext_plugin'] ?? null;
        $name = $request->route()->defaults['_ext_action'] ?? null;
        $action = (is_string($plugin) && is_string($name)) ? $this->extensionActions->get($plugin, $name) : null;

        abort_if($action === null, 404);

        $area = app($action->getAreaClass());
        $models = $area->resolve($request->route()->parameters());

        abort_unless($area->canView($request->user(), $models), 403);

        $models = $this->applyBinds($action->getBinds(), $models, $request);

        abort_unless($action->isAuthorized($request->user(), $models), 403);

        $input = $request->all();
        if (($form = $action->getForm()) !== null) {
            $rules = $form->validationRules();
            if ($rules !== []) {
                $input = array_merge($input, $request->validate($rules));
            }
        }

        $result = $action->run($models, $input, $request);

        return $result instanceof RedirectResponse ? $result : back();
    }

    private function resolvePage(Request $request): AbstractPage
    {
        $id = $request->route()->defaults['_page'] ?? null;
        $page = is_string($id) ? $this->registry->get($id) : null;

        abort_if($page === null, 404);

        return $page;
    }

    private function resolveAction(AbstractPage $page, Request $request): PageAction
    {
        $id = $request->route()->defaults['_action'] ?? null;

        foreach ($page->allActions() as $action) {
            if ($action->id() === $id) {
                return $action;
            }
        }

        abort(404);
    }

    private function resolveEndpoint(AbstractPage $page, Request $request): DataEndpoint
    {
        $id = $request->route()->defaults['_endpoint'] ?? null;

        foreach ($page->allData() as $endpoint) {
            if ($endpoint->id() === $id) {
                return $endpoint;
            }
        }

        abort(404);
    }

    /**
     * @param  array<int, \App\Pages\Binding>  $binds
     * @param  array<string, Model>  $models
     * @return array<string, Model>
     */
    private function applyBinds(array $binds, array $models, Request $request): array
    {
        foreach ($binds as $binding) {
            $value = $request->input($binding->param, $request->route($binding->param));
            $models[$binding->param] = $binding->resolve($value, $models);
        }

        return $models;
    }

    /**
     * @param  array<string, Model>  $models
     * @return array<string, array<string, mixed>>
     */
    private function actionMap(AbstractPage $page, array $models): array
    {
        $map = [];

        foreach ($page->allActions() as $action) {
            if (! $action->hasAuthorization()) {
                continue;
            }

            $map[$action->id()] = [
                'method' => $action->getMethod(),
                'url' => route($action->getRouteName("{$page->routeName()}.{$action->id()}"), $models),
                'confirm' => $action->getConfirm(),
                'confirmText' => $action->getConfirmText(),
                'form' => $action->getForm()?->toArray(),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, Model>  $models
     * @return array<string, array<string, mixed>>
     */
    private function dataMap(AbstractPage $page, array $models): array
    {
        $map = [];

        foreach ($page->allData() as $endpoint) {
            if (! $endpoint->hasAuthorization()) {
                continue;
            }

            $map[$endpoint->id()] = [
                'method' => $endpoint->getMethod(),
                'url' => route($endpoint->getRouteName("{$page->routeName()}.{$endpoint->id()}"), $models),
            ];
        }

        return $map;
    }
}
