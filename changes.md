# Changes — `wip` commit (`116b0141`)

> Branch `feat/4-1-plugin-upgrade`, off `4.x`. 186 files changed, ~10,864 insertions / ~3,951 deletions.
> This single commit lands the **Plugin Page Framework**: a schema-driven, PHP-authored
> page system that lets first-party features *and* installable plugins build full UI pages
> (and extend existing ones) without shipping any JavaScript.

This document inventories what the commit does. For *how it fits together*, see
`plugin_architecture.md`. For an honest appraisal, see `critical_review.md`.

---

## 1. New subsystem: `app/Pages/` (the framework core)

A whole new namespace describing pages declaratively in PHP.

| Area | Files | Purpose |
|------|-------|---------|
| Page/area contracts | `AbstractPage`, `AbstractArea`, `Areas/ServerArea`, `Areas/SiteArea` | A page declares an `id`, an `area`, a `slug`, and a `schema()` tree. Areas are fixed, core-only route contexts (`servers/{server}` / `…/sites/{site}`). |
| Scoping | `Binding` | One declarative "this model is resolved from this param and must belong to that parent" descriptor. Shared by areas, actions, data endpoints and extension actions — makes IDOR structural. |
| Registry | `PageRegistry`, `Contracts/SchemaNode` | Boot-time, idempotent (first-wins) registry of areas + pages. `SchemaNode` is the one wire contract every component implements (`id`/`type`/`children`/`serialize`). |
| Behaviour | `PageAction`, `DataEndpoint` | A mutation (`POST {page}/actions/{id}`) and a JSON read (`{page}/data/{id}`). Both carry input binds, an authorize closure, and a handler that delegates to existing `app/Actions/*`. |
| Closure engine | `Schema/EvaluatesClosures`, `Schema/EvaluationContext` | Filament-style reflection DI: any setter value may be `fn (Site $site, array $input) => …`; params resolve by name then type. The page tree is built **model-free**; models only appear at serialize/dispatch time. |
| Components | `Components/AbstractComponent`, `Page`, `Card`, `Entry`, `DynamicCardRow`, `DynamicButton`, `DynamicDialog`, `DynamicTable`, `RowAction`, `DynamicLogView`, `DynamicCodeEditor`, `DynamicAlert`, `Control` | The schema-node palette. `Entry` is the Filament-infolist-style settings row; `DynamicTable` wraps an `app/Tables/*` inertia-table class; `Control` is the escape hatch to a registered React control. |
| Field DSL | `Components/Forms/Field`, `TextInput`, `Password`, `Textarea`, `Checkbox`, `Select`, `Repeater`, `Hidden`, `Control` | Fluent form-field builders that emit the frozen `App\DTOs\DynamicField` wire shape. `Select::make('version')->options(fn (Server $server) => …)`. |
| Extension layer | `ExtensionRegistry`, `ExtensionActionRegistry` | Holds plugin contributions (page extenders, standalone extension actions); applies them atomically per-plugin at render time. |
| Errors | `PageComponentException` | Thrown on duplicate node/action ids and address collisions. |

### Concrete pages shipped (migrated off legacy controllers)
`app/Pages/Server/ServerPocPage` (deletable proof-of-concept) plus full cutovers:
- **`SiteSettings/SiteSettingsPage`** (+ 8 section components: `SourceControl`, `BasicAuth`, `Statistics`, `ForceSsl`, `ProxiedEndpoints`, `DeleteSiteCard`, `VhostEditor`, `Section` base)
- **`Redirects/RedirectsPage`**
- **`SiteCronJobs/SiteCronJobsPage`**
- **`HostedDomains/HostedDomainsPage`**
- **`SiteStats/SiteStatsPage`**
- **`SiteTooling/SiteToolingPage`**
- **`SiteWorkers/SiteWorkersPage`**

---

## 2. Routing & the single controller

- **`app/Http/Controllers/PageController`** — *one* controller for every framework page.
  `show()` / `action()` / `data()` / `extensionAction()` all run the same pipeline:
  resolve page from registry by the route's `_page` default → resolve the area binding
  chain (404 on foreign/mismatched ids) → `canView` (403) → optional per-page `guard()` →
  serialize / dispatch. Handlers are **never** embedded in the route action, so routes stay
  cacheable.
- **`app/Actions/Pages/RegisterPageRoutes`** — routing strategy "(a)": registers a real,
  named route per page/action/data endpoint at boot, *after* Spatie attribute routes exist
  (so the collision check sees the full router). Skips entirely when routes are cached.
  Auto-derives action/data route names as `{page-route}.{id}` — this is what reproduces the
  legacy `site-settings.*`, `redirects.*`, `workers.*` names with zero `->routeName()` calls.
  Includes URI-normalized collision detection and an action/data name-collision guard.
- **`app/Console/Commands/Pages/ListPageIdsCommand`** (`pages:ids`, `--check`) +
  **`resources/pages-ids.json`** — a committed snapshot of every page/action/data/slot
  address. `--check` is a CI gate so an address can't silently change across a migration.
- **`app/Providers/PagesServiceProvider`** — registers the core areas/pages and schedules
  `RegisterPageRoutes` in a `booted()` callback. Registered **last** in `config/app.php`
  (after the plugins provider) so it runs after plugins have registered their pages.

---

## 3. Plugin SDK changes (`app/Plugins/`)

### New builders
- **`RegisterPage`** — `RegisterPage::make(MyPage::class)->register()`.
- **`ExtendPage`** — *one* mechanism to modify any page (framework or hard-coded): declarative
  `add` / `addRow` / `replace` / `remove` ops with `after`/`before` placement; component
  factories receive resolved models at render time.
- **`ExtendTable`** — register an inertia-table `beforeQuery` hook against a table id (lazily,
  deduped per render, to survive Octane double-apply).
- **`RegisterExtensionAction`** — a standalone server handler for hard-coded pages that have
  nowhere to POST. Compositional: `->area()` supplies route context through the area pipeline,
  `->bind()` adds input-borne children; routed `POST {area}/ext/{plugin}/{action}`.

### Lifecycle refactor (the QoL fix behind the routing change)
- **`InvalidatePluginState`** (new) — the shared tail for *every* lifecycle change. Clears the
  active-plugin cache, flushes the **route cache** *and* the **Ziggy client route-script cache**
  (`GetZiggyRoutes::forgetCache()` — otherwise client `route()` lookups for page routes go stale
  forever), recomputes the bootstrap version and broadcasts `bootstrap.invalidated`.
- **`FlushRouteCaches`** (new) — `route:clear` wrapper, added to the lifecycle.
- **`InstallPlugin` / `EnablePlugin` / `DisablePlugin` / `UninstallPlugin`** — each refactored to
  call `InvalidatePluginState` instead of duplicating the tail.
- **`BootPlugins`** — now sets the "current plugin" on both extension registries around each
  `boot()` call (so contributions are attributed) and resets in `finally`.

### Removed (legacy plugin system retired)
- `app/Plugins/LegacyPlugins.php`, `app/Console/Commands/Plugins/LoadLegacyPluginsCommand.php`,
  `app/Facades/Plugins.php`, `tests/Unit/Plugins/LegacyPluginsTest.php`.
- `app/Plugins/Interfaces/PluginInterface.php` is now just a **`class_alias` shim** to the
  flattened `App\Plugins\PluginInterface` (kept one beta cycle for published plugins).

---

## 4. Page extension at the HTTP layer

- **`app/Http/Middleware/AttachPageExtensions`** (alias `page-extensions:{pageId},{areaId}`) —
  resolves the page's area models and shares plugin **slot** contributions as an Inertia
  `slots` prop, so a hard-coded page can host plugin panels via `<PageSlot name="…"/>`.
- **`HandleInertiaRequests::share`** — now resolves scalar `server`/`site` route params to models
  (framework `PageController` routes leave them as id strings; the old `share()` assumed bound
  models). This was the blocker behind the 500 "load() on string" error.
- **`app/Http/Kernel`** — registers the new middleware alias.

---

## 5. Tables (`app/Tables/`)

New inertia-table classes backing the migrated pages: `WorkerTable`, `CronJobTable`,
`RedirectTable`, plus tweaks to `HostedDomainTable`. Their status enums
(`WorkerStatus`, `CronjobStatus`, `RedirectStatus`) now `implements
Forjed\InertiaTable\Contracts\HasTableDisplay` so the existing `getText()`/`getColor()`
drive coloured status badges.

---

## 6. TypeScript type enforcement

- **`composer.json` / `composer.lock`** — adds `spatie/laravel-typescript-transformer`.
- **`app/Providers/TypeScriptTransformerServiceProvider`** — generates
  `resources/js/types/generated.d.ts` (string-literal unions for every backed enum in
  `app/Enums`).
- **`resources/js/types/typescript-transformer-manifest.json`** + a drift-gate test
  (`typescript:transform` must be a no-op against the committed file).

---

## 7. Deleted legacy controllers & JS (replaced by framework pages)

| Deleted controller | Replaced by |
|--------------------|-------------|
| `SiteSettingController` (353 lines) | `SiteSettingsPage` + sections |
| `HostedDomainController` (web; 173 lines) | `HostedDomainsPage` + 3 controls |
| `RedirectController` (web; 56 lines) | `RedirectsPage` |
| `CronJobController` (site methods only) | `SiteCronJobsPage` (server methods kept) |
| `SiteStatsController::index` | `SiteStatsPage` + `stats-dashboard` control |
| `SiteToolingController::index` | `SiteToolingPage` + `tooling-panel` control |
| `WorkerController::site` | `SiteWorkersPage` + `worker-actions`/`workers-header` controls |

Deleted React: the whole legacy `resources/js/pages/{site-settings, redirects, hosted-domains}`
trees and `workers/components/columns.tsx`, plus migrated `site-tooling`/`site-stats` indexes.
API controllers were kept in every case; only the web/Inertia surfaces were removed.

---

## 8. Frontend: the dynamic renderer (`resources/js/`)

- **`pages/dynamic/`** — the renderer: `page.tsx` (entry, wraps in an area layout),
  `page-context.tsx` (action/data URL maps + `dispatchAction`/`interpolateParams`),
  `component-registry.ts` (mutable `Map`), `resolve-node.tsx` (id-keyed recursion),
  `area-layouts.tsx`, `components/*` (page, card, card-row, button, alert, table, log-view,
  code-editor, control), and `register-components.ts`.
- **Pluggable controls** — `controls/registry.ts` exposing the four seams:
  `registerFieldControl` / `registerPanelControl` / `registerRowActionsControl`, plus the
  inertia-table library's own `registerCellComponent` for cell controls. `register-controls.ts`
  wires up the first-party ones.
- **Per-feature controls** moved out of deleted indexes into `controls/` folders:
  hosted-domains (`certificate-cell`, `ssl-matcher`, `ssl-menu`), workers (`worker-actions`,
  `workers-header`), site-tooling (`tooling-panel`), site-stats (`stats-dashboard`),
  redirects (`redirect-mode-cell`).
- **Dialogs** — `dialogs/dynamic-dialog.tsx` (form / sheet / confirm / confirmText / logView /
  editor modes) and `dynamic-preview-dialog.tsx`, registered in `dialogs/registry.ts`. The
  **`stores/dialog-store.ts`** dialog store became a bounded stack: `open()` returns an
  `instanceId`, `close` is by-instance, and `closeTopByKey` serves the ~65 `dialog.x.close()`
  call sites.
- **`components/page-slot.tsx`** — renders the `slots[name]` plugin contributions.
- **`components/ui/dynamic-field.tsx`** — hardened (controlled inputs, falsy-safe defaults) and
  generalized: `type:'component'` → `getFieldControl(...)`, new `type:'hidden'`, repeater UI.
- **`components/vito-table.tsx`** — `copyable` cells now render Vito's `CopyableBadge`.
- **Types** — new `types/dynamic-page.d.ts` (node-type unions), `generated.d.ts`,
  `dynamic-field-config.d.ts` additions.

---

## 9. DTO changes

- **`DynamicField`** — adds `->rules()`, `->repeater()`, `->hidden()`, and `->component(?name)`
  (now stores+emits the `component` key). Rules/fields only serialize when set, so the
  bootstrap hash doesn't churn.
- **`DynamicForm`** — adds `validationRules()` (supports nested `name.*.sub` repeater rules).

---

## 10. Tests

New suites mirror the framework: `tests/Unit/Pages/*` (Binding, Controls, DefaultAuthorize,
DynamicFormRules, EvaluatesClosures, ExtensionRegistry, PageHarvest, PageRegistry),
`tests/Feature/Pages/*` (ExtendPage, ExtendTable, ExtensionAction, PageAction, PageController,
PageIdsCommand, PageSlot, SiteSettingsPage, **SiteSettingsWireSnapshot**, TypeScriptTransformer),
`tests/Unit/Tables/WorkerTableTest`, `tests/Feature/Plugins/InvalidatePluginStateTest`. Existing
feature tests (`HostedDomainsTest`, `RedirectsTest`, `SiteCronjobTest`, `SiteStatsTest`,
`SiteToolingTest`, `WorkersTest`) were ported to assert `component('dynamic/page')` + the
`tables:{id}` props while keeping their mutation assertions unchanged.

The **`SiteSettingsWireSnapshot`** test pins a structural fingerprint of the serialized page to
`fixtures/site-settings-wire.json` (captured from pre-refactor code) — the proof that the
fluent/Filament authoring refactor stayed byte-identical on the wire.

> **Verification status (per the build checkpoint):** full PHP suite green (~1699 tests),
> `tsc --noEmit` clean, `pages:ids --check` matches. Frontend rendering is **tsc-verified only**
> — not visually confirmed in a browser. The user runs visual verification.
