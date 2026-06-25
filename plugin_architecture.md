# Plugin Page Framework — Architecture

> A schema-driven page system for Vito. Features and installable plugins author full UI pages
> **in PHP** — no JavaScript required — and extend each other's pages through a single
> declarative mechanism. The frontend is a generic renderer that turns a serialized schema tree
> into React. This document describes the design as it exists in the `wip` commit.

---

## 1. Mental model

```
  PHP author                          Wire (Inertia props)                 React renderer
  ──────────                          ────────────────────                 ──────────────
  AbstractPage::schema()   ─serialize→  schema:  [ {id,type,children,…} ]   resolve-node.tsx
    Card / Entry / Table              actions: { id: {method,url,form} }    component-registry (Map)
    PageAction / DataEndpoint         data:    { id: {method,url} }         control registries
    (all model access in closures)    tables:{id}: <paginator>             dynamic-dialog stack
                                      slots:   { name: [nodes] }            page-slot.tsx
```

The author writes a **model-free** component tree; every dynamic value is a closure. The
framework serializes that tree per-request through an `EvaluationContext` (the only carrier of
models/user/request into the closures), harvests the behaviour (actions/data) co-located on the
nodes, and ships JSON. The browser never sees PHP; the server never sees JSX.

Three properties are load-bearing throughout:

1. **One node contract** (`SchemaNode`) so tree walks — placement, collision, harvesting,
   URL resolution — are written once, not per component.
2. **One scoping primitive** (`Binding`) so cross-project / IDOR safety is *structural*, not a
   check each handler must remember.
3. **Closures everywhere** so a page can be declared once at boot and re-rendered for any
   request without rebuilding.

---

## 2. The layers

### 2.1 Areas — fixed route contexts (`app/Pages/AbstractArea.php`)

An **Area** is the context a page lives in: its URL prefix and params, which models those
params resolve to, the project boundary / `canView` gate, the middleware, and the frontend
layout. Areas are **core-only and fixed** — there is deliberately no `RegisterArea` hook;
plugins register pages *into* `ServerArea` (`servers/{server}`) or `SiteArea`
(`servers/{server}/sites/{site}`).

```php
abstract public function routePrefix(): string;          // 'servers/{server}/sites/{site}'
abstract public function bindings(): array;               // Binding chain, root-first
abstract public function canView(User $u, array $models): bool;
abstract public function layout(): string;                // frontend area-layout key
public function middleware(): array { return ['auth', 'has-project']; }
public function resolve(array $params): array { … }       // runs the binding chain → models
```

`resolve()` walks the bindings in order, enforcing parentage at each step (a site must belong to
the server in the URL, or 404). The same method is reused for `extensionAction` routes.

### 2.2 Bindings — the one scoping primitive (`app/Pages/Binding.php`)

```php
Binding::root('server', Server::class);
Binding::make('site', Site::class, scopedTo: 'server');   // site.server_id === server.id or 404
```

`resolve($value, $alreadyResolved)` accepts either a raw id or an already-bound model; for child
bindings it asserts `model.{parent}_id === parent.id`. This single class scopes **areas, page
actions, data endpoints, and extension actions**. Because a page action declares
`->bind('redirect', Redirect::class, 'site')`, an attacker passing another project's redirect id
gets a 404 from the framework — no per-handler check needed.

### 2.3 Pages — declarative UI (`app/Pages/AbstractPage.php`)

```php
abstract public static function id(): string;     // 'site-settings'  (the stable address root)
abstract public function area(): AbstractArea;
abstract public function slug(): string;          // 'settings' → appended to the area prefix
public function schema(): array;                  // SchemaNode[] — built MODEL-FREE
public function headless(): array;                // PageAction|DataEndpoint with no UI node
public function defaultAuthorize(): ?Closure;     // write gate inherited by actions lacking one
public function guard(array $models): void;        // optional per-page authz beyond canView
```

Two derived sets are computed by walking the tree:

- **`allActions()`** = actions harvested from every node (`$node->actions()`, recursively) ∪
  legacy `actions()` ∪ `headless()` PageActions. The `defaultAuthorize()` gate is injected into
  any action that didn't declare its own. Duplicate ids throw.
- **`allData()`** = the same for `DataEndpoint`s.

This *harvest* is why authoring reads like Filament: you co-locate a `PageAction` on the `Entry`
that triggers it, and the framework finds it — you never maintain a separate action list.

### 2.4 Schema nodes — the component palette (`app/Pages/Components/`)

Every component implements `SchemaNode` (`id`, `type`, `children`, `serialize`) via
`AbstractComponent`, which provides:

- **Dotted-address composition**: `Entry::make('php-version')` inside a `Card::make('details-card')`
  serializes with id `details-card.php-version`. The id *is* the extension-targeting address and
  the React `key` — never index-based.
- **Closure-aware visibility** (`->visible(fn (Site $site) => …)`) — a hidden node serializes to
  `null` and is filtered out.
- **Behaviour bubbling**: `actions()` / `data()` default to `[]`; a node returns the PageActions /
  DataEndpoints it carries (an `Entry`'s edit action, a `DynamicTable`'s row/header actions) so the
  page harvest finds them.

Palette highlights:

| Node | `type` | Role |
|------|--------|------|
| `Page` | `page` | Root container (title/description + children). |
| `Card` | `card` | Grouping container; `->schema([...])` prefixes bare-key children. |
| `Entry` | `card-row` | Filament-infolist row. `->copyable()/->badge()/->link()/->code()` + `->state(fn…)`; attach `->action(PageAction)` to make it an editable button opening a modal. |
| `DynamicTable` | `table` | Wraps an `app/Tables/*` inertia-table class. Ships **shell only**; data is a sibling prop (§3.3). |
| `RowAction` / `DynamicButton` | — | Triggers (dispatch an action or open a dialog). |
| `DynamicDialog` | `dialog` | Form / sheet / confirm / log-view / editor modal. |
| `DynamicLogView`, `DynamicCodeEditor`, `DynamicAlert` | … | Specialized panels. |
| `Control` | `control` | Escape hatch → a registered React panel control (§4). |

**Form-field DSL** (`Components/Forms/`): `TextInput`, `Password`, `Textarea`, `Checkbox`,
`Select`, `Repeater`, `Hidden`, `Control` — fluent builders whose `resolve(ctx)` emits the
**frozen `App\DTOs\DynamicField`** wire shape. `Select::make('version')->options(fn (Server $server) => …)`.
Keeping the wire contract frozen is why the renderer didn't have to change as authoring evolved.

### 2.5 Behaviour — actions & data (`PageAction`, `DataEndpoint`)

```php
PageAction::make('update-php-version')->patch()
    ->modalHeading('Change PHP version')
    ->form([Select::make('version')->options(fn (Server $server) => $server->installedPHPVersions())])
    ->authorize(fn (User $u, Site $s, Server $srv) => $u->can('update', [$s, $srv]))   // or inherit defaultAuthorize
    ->run(fn (Site $site, array $input) => app(UpdatePHPVersion::class)->update($site, $input))
    ->success('PHP version updated successfully.');
```

- **`PageAction`** → `POST|PATCH|PUT|DELETE {page}/actions/{id}`. Carries input `bind`s, a
  mandatory write `authorize` (or inherits `defaultAuthorize`), an optional `DynamicForm`
  (server-side validation → 422), and a `run()` closure that **delegates to an existing
  `app/Actions/*` business-logic class** — the framework owns transport, not logic.
- **`DataEndpoint`** → `GET|POST {page}/data/{id}` returning JSON (log polling, editor load,
  async selects, VHost preview). Read-only endpoints may `->public()` (inherit the area
  `canView`); otherwise an explicit `authorize` is required.

Both use the same closure-DI engine, so `fn (Site $site, array $input)` and the legacy
`fn (array $models, array $input, Request $request)` both work — that's what let headless actions
be ported verbatim.

### 2.6 The closure engine (`app/Pages/Schema/`)

`EvaluatesClosures::evaluate($value, $ctx, $named=[])` is the heart. If `$value` is a closure it
reflects the parameters and resolves each:

1. **By name** — per-call overrides, then `models / input / request / user / record`, then any
   bound model key (`site`, `server`, …).
2. **By type** — `Request`, `User`/subclass, or any `Model` matching a bound model or the row
   `record`.
3. Else the parameter default, else `null` if nullable, else throw.

`EvaluationContext` is an immutable carrier (`models`, `user`, `request`, `record`, `input`) with
`withRecord()` / `withInput()` builders. It is the *sole* path for request state to reach a
deferred closure, which is what makes "build the tree model-free at boot, evaluate per request"
sound.

---

## 3. The request pipeline (`app/Http/Controllers/PageController.php`)

One controller serves every framework page. All four entry points share the spine:

```
resolve page from registry by route default `_page`   (404 if absent → disabled plugin is safe)
 → area->resolve(route params)                          (binding chain; 404 on foreign/mismatch)
 → area->canView(user, models)                          (403)
 → page->guard(models)                                  (optional per-page authz)
 → serialize / dispatch
```

### 3.1 `show()` → `Inertia::render('dynamic/page', …)`
Serializes each schema node through the context, applies plugin extenders
(`ExtensionRegistry::apply`), builds the table props, and returns:
`area`, `layout`, `page`, `schema`, `actions` (id→{method,url,form}), `data` (id→{method,url}),
plus `tables:{id}` siblings and the area's `sharedProps`. Serialization is wrapped in a
try/catch that `report()`s and degrades to an empty schema rather than 500-ing the whole page.

### 3.2 `action()` / `data()`
Re-resolve the page & area, re-check `canView`, **apply the action's input binds** (the IDOR
gate for body/query params), check the action's own `authorize`, validate the form (→ 422), then
`dispatch()`. Data endpoints either inherit `canView` or enforce their own `authorize`.

### 3.3 Tables: shell/data split
`tableProps()` exposes each `DynamicTable`'s data as a **closure-valued** sibling prop
`tables:{id}`. Inertia only evaluates closures the request actually asks for, so a sort / realtime
reload (`router.reload({only:['tables:{id}']})`) rebuilds *just the rows*, never the page schema.
`DynamicTable::buildData()` runs the node's `query` closure and `simplePaginate()`s through the
inertia-table class.

### 3.4 Why the route action is static
Routes point at `[PageController::class, 'show'|'action'|'data']` with the page/handler carried
as **route defaults** (`_page`, `_action`, `_endpoint`). Closures in route actions break
`route:cache`; route *defaults* survive it. The controller looks the live object up from the
registry at request time — so a stale cached route for an uninstalled plugin simply resolves to
`null` → 404.

---

## 4. Frontend renderer (`resources/js/pages/dynamic/`)

- **`page.tsx`** — entry. Reads `area`/`layout`/`schema`/`actions`/`data`, wraps in the area
  layout (`area-layouts.tsx`), provides `DynamicPageProvider`, renders `<ResolveNodes>`.
- **`page-context.tsx`** — holds the `actions`/`data` URL maps; exposes `dispatchAction()`
  (fires `router.{post,patch,…}` with `preserveScroll`) and `interpolateParams()` (`:id` → row
  value).
- **`component-registry.ts`** — a **mutable `Map<type, Component>`** with
  `registerComponent`/`getComponent`. Mutable on purpose: v2 plugin JS bundles will register into
  it at runtime.
- **`resolve-node.tsx`** — `ResolveNode` looks a node's component up by `type`; `ResolveNodes`
  maps an array keyed by **`node.id`** (stable address = stable React key → no state reattachment
  when extenders reorder).

### 4.1 The four control seams
The schema can name a custom React control where declarative nodes can't express the UI:

| Seam | Register / get | Consumed in | Example |
|------|----------------|-------------|---------|
| **Field** | `registerFieldControl` / `getFieldControl` | `dynamic-field.tsx` `type:'component'` | `ssl-matcher`, `server-provider` |
| **Cell** | the inertia-table lib's `registerCellComponent` | VitoTable cell render | `certificate-cell`, `redirect-mode-cell` |
| **Panel** | `registerPanelControl` / `getPanelControl` | `components/control.tsx` (`type:'control'`) | `stats-dashboard`, `tooling-panel` |
| **Row actions** | `registerRowActionsControl` / `getRowActionsControl` | `table.tsx` actions column | `worker-actions` |

Backend counterparts: `Control::make()->using('name')->with(props)`, `Column->component('name')`,
`DynamicTable->rowActionsControl('name')`, `DynamicField->component('name')`. This is the seam by
which a complex legacy page (SSL matching, GoAccess dashboard) becomes a framework page without
the framework having to model every interaction.

### 4.2 Dialogs (`components/dialogs/`, `stores/dialog-store.ts`)
`dynamic-dialog.tsx` renders form / sheet / confirm / confirmText / log-view / editor modes from a
`DialogNode`. The dialog store is a **bounded stack**: `open(key, props)` returns a per-entry
`instanceId`; `closeById(id)` closes exactly that entry (the ~65 `dialog.x.close()` callers go
through `closeTopByKey`); re-opening the same key on top *replaces* it (single-active semantics).
This replaced a single-slot store that couldn't express "a dialog opened from inside a dialog".

---

## 5. The extension system

### 5.1 `ExtendPage` → `ExtensionRegistry` (`app/Plugins/ExtendPage.php`, `app/Pages/ExtensionRegistry.php`)
One mechanism extends *any* page. A plugin stages declarative ops — `add` / `addRow` / `replace` /
`remove` with `after`/`before` placement — whose component factories receive resolved models at
render time. Plugins **never touch the raw schema tree**; they describe edits to addresses.

At render, `ExtensionRegistry::apply(pageId, schema, ctx)` replays each plugin's ops onto the
serialized tree with two guarantees:

- **Atomic per plugin per page** — a plugin's ops are staged on a working copy and committed only
  if *all* succeed; any throw or address collision drops that plugin's whole contribution for that
  render and logs a `PluginError`. A torn half-rendered panel is worse than an absent one.
- **Render failure never auto-disables** the plugin (unlike a boot failure, which does).

Missing placement targets *append + warn* (forward-compatible); address collisions are fatal to
the contribution (atomicity). `collectSlots()` is the variant that feeds hard-coded pages via the
middleware.

### 5.2 Slots for hard-coded pages (`AttachPageExtensions` + `PageSlot`)
A page that *isn't* a framework page still hosts plugin panels: the
`page-extensions:{pageId},{areaId}` middleware resolves the area models, collects slot-anchored
contributions, and shares them as an Inertia `slots` prop; the page drops a
`<PageSlot name="workers.before-table"/>` where contributions should land.

### 5.3 `RegisterExtensionAction` (`app/Plugins/RegisterExtensionAction.php`)
Hard-coded pages have nowhere to POST a plugin's mutation, so a plugin registers a standalone
handler: `->area(SiteArea::class)` roots it in the full area pipeline (binding chain + project
boundary + `canView`), `->bind(...)` adds input-borne children, a **mandatory** `authorize`
closure is the write gate. Routed `POST {area-prefix}/ext/{plugin}/{action}` — the area prefix
makes IDOR structural here too.

### 5.4 `ExtendTable`
Registers an inertia-table `beforeQuery` hook against a table id, deduped per render (the
library's `HookRegistry` is process-global → must not double-apply under Octane).

---

## 6. Plugin lifecycle & routing coherence

```
PluginsServiceProvider::boot()  ─app->booted()→  BootPlugins::handle()
    for each enabled plugin:
        ExtensionRegistry::setCurrentPlugin(name)        ← attributes contributions
        $instance->boot()    // calls RegisterPage / ExtendPage / RegisterExtensionAction
        (on throw) is_enabled = false; PluginError::create
PagesServiceProvider::boot()    ─app->booted()→  RegisterPageRoutes::handle()   ← registered LAST
```

`PagesServiceProvider` is registered **after** the plugins provider in `config/app.php`, so its
`booted()` callback runs *after* `BootPlugins`, meaning plugin pages are in the registry before
routes are generated. `RegisterPageRoutes` (strategy "a"):

1. **Skips entirely when routes are cached** — the compiled cache already has them.
2. Registers extension-action routes, then one GET per page + a route per action/data endpoint,
   all pointing at the static `PageController` with the page/handler in route defaults.
3. **Collision-checks against the full router** (Spatie attribute routes already exist), with
   `{param}` segments normalized, and a separate action/data **name**-collision guard.
4. Auto-derives names `{page-route}.{id}` → reproduces every legacy route name for free.

Because routes are generated from the *live registry*, install/enable/disable must invalidate the
**route cache and the Ziggy client script** — that's `InvalidatePluginState` (the shared lifecycle
tail): `PluginCache::clear()` + `FlushRouteCaches` (`route:clear`) + `GetZiggyRoutes::forgetCache()`
+ bootstrap recompute + `bootstrap.invalidated` broadcast. The lifecycle actions
(`InstallPlugin`/`EnablePlugin`/`DisablePlugin`/`UninstallPlugin`) all funnel through it.

---

## 7. Contract enforcement (the safety nets)

- **`pages:ids --check`** + `resources/pages-ids.json` — every page/action/data/slot address is
  snapshotted and CI-gated, so a migration can't silently move an address that another page or a
  plugin depends on.
- **`SiteSettingsWireSnapshotTest`** + `fixtures/site-settings-wire.json` — a structural
  fingerprint of the serialized page captured from pre-refactor code; the proof the authoring
  layer can be reworked (Filament-fluent, section extraction) while the wire JSON stays
  byte-identical and the renderer is untouched.
- **TypeScript transformer** — `generated.d.ts` enum unions are regenerated and drift-gated, so
  backend enums and frontend types can't diverge.
- **Frozen `DynamicField`/`DynamicForm` wire shape** — the firewall between authoring churn and
  the renderer.

---

## 8. Authoring example (the end-to-end shape)

```php
final class SiteSettingsPage extends AbstractPage
{
    public static function id(): string { return 'site-settings'; }
    public function area(): AbstractArea { return app(SiteArea::class); }
    public function slug(): string { return 'settings'; }
    public function routeName(): string { return 'site-settings'; }

    public function defaultAuthorize(): ?Closure
    {
        return fn (User $u, Site $s, Server $srv) => $u->can('update', [$s, $srv]);
    }

    public function schema(): array
    {
        $details = Card::make('details-card')->title('Site details')->schema([
            Entry::make('id')->label('ID')->copyable()->state(fn (Site $s) => (string) $s->id),
            ...(new SourceControl)->rows(),           // a self-contained Section
            Entry::make('php-version')->label('PHP version')
                ->state(fn (Site $s) => $s->php_version)
                ->action(PageAction::make('update-php-version')->patch()
                    ->form([Select::make('version')->options(fn (Server $srv) => $srv->installedPHPVersions())])
                    ->run(fn (Site $s, array $in) => app(UpdatePHPVersion::class)->update($s, $in))),
        ]);

        return [ Page::make('site-settings')->title('Settings')->add($details, (new DeleteSiteCard)->card()) ];
    }

    public function headless(): array { return (new ProxiedEndpoints)->headless(); }
}
```

This declares the page, its rows, an editable PHP-version entry with a validated modal that
delegates to the real `UpdatePHPVersion` action, and a write gate — all model-free, all in PHP,
serving the exact `site-settings.*` routes the rest of the app already calls. A plugin extends it
with:

```php
ExtendPage::make('site-settings')
    ->addRow('details-card', fn (array $m) => Entry::make('my-plugin.flag')->label('My Flag')->state(…), after: 'php-version')
    ->register();
```
