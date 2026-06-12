<?php

namespace App\Http\Middleware;

use App\Pages\ExtensionRegistry;
use App\Pages\PageRegistry;
use App\Pages\Schema\EvaluationContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Delivers plugin slot contributions to a hard-coded (non-framework) page. Applied
 * per route as `page-extensions:{pageId},{areaId}`. Resolves the page's models via
 * the declared Area (so factories get scoped models) and shares the slot contributions
 * as an Inertia `slots` prop ({ slotName: nodes[] }) for `<PageSlot>` to render. A
 * failing/contextless resolution never breaks the underlying page.
 */
class AttachPageExtensions
{
    public function __construct(
        private readonly PageRegistry $pages,
        private readonly ExtensionRegistry $extensions,
    ) {}

    public function handle(Request $request, Closure $next, string $pageId, string $areaId): Response
    {
        $area = $this->pages->area($areaId);

        if ($area !== null && $this->extensions->hasExtenders($pageId)) {
            try {
                $models = $area->resolve($request->route()->parameters());
                $ctx = EvaluationContext::make($models, $request->user(), $request);
                Inertia::share('slots', $this->extensions->collectSlots($pageId, $ctx));
            } catch (Throwable) {
                // Never let an extension-context failure break the host page.
            }
        }

        return $next($request);
    }
}
