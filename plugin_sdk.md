# Vito Plugin SDK — Architecture & Vision

> **Status:** Design / RFC. Target: the "React-first, done right" path (Option B of
> `greenfield_approach.md`).
> **Scope:** the full Software Development Kit a third party uses to build, package, and ship a
> Vito plugin — both the PHP authoring surface and the published frontend package.
> **Builds on:** the Plugin Page Framework already landed in `116b0141` (`app/Pages/*`, `ExtendPage`,
> the control registries, the TypeScript transformer). This document describes how that 70%-complete
> draft becomes a real, safe, third-party SDK.

---

## Foreword

Vito today is extended by people who have the Vito source open in another window. The Plugin Page
Framework changed the *shape* of that — pages are now declarative PHP that the framework serializes
and a generic React renderer draws — but it stopped short of being a **kit you hand to a stranger**.
The renderer's type contract is hand-maintained, routes are registered dynamically from a live
registry, extension targets are raw string addresses into core's internal layout, and plugins still
cannot ship a line of JavaScript. Each of those is fine when the only author is the core team. None
of them is acceptable when the author is `acme/vito-backups` published to a marketplace and pinned to
a version of Vito the author has never run.

A Plugin SDK is a *promise*: "build against these contracts, and your plugin keeps working across Vito
releases; ship rich UI as easily as core does; and your users can trust what they install." This
document is the architecture for keeping that promise. It treats the existing framework as the engine
and designs the **kit, the contracts, and the guarantees** around it.

---

## 1. Purpose

The SDK exists to let a developer who has **never seen Vito's source** build a production-quality
plugin that:

1. **Adds pages** to Vito's server and site areas (G2).
2. **Extends existing pages** — adds a card, a row, a table column, an action, a settings section —
   without patching core (G3).
3. **Authors UI in PHP** for the CRUD-shaped 80%, using a fluent, Filament-like builder (G4).
4. **Ships rich React UI** — terminals, live logs, charts, dependent async forms — for the 20% the
   schema can't express, as a first-class capability rather than a core-only escape hatch (G5).
5. **Is safe by construction** — project-scoped, policy-authorized, capability-declared, and unable to
   take the whole panel down when it has a bug (G6).
6. **Survives upgrades** — written against **versioned, typed contracts** with a deprecation cycle, so
   a Vito minor release does not silently break it.

The SDK is the union of three deliverables:

| Deliverable | What it is |
|-------------|-----------|
| **`vito/plugin-sdk` (PHP)** | The authoring surface: `AbstractPage`, the component/field/action builders, `ExtendPage`, `RegisterExtensionAction`, the typed extension-point contracts, and the plugin lifecycle interface. Already ~exists under `App\Plugins\*` / `App\Pages\*`; the SDK extracts and stabilizes it. |
| **`@vito/plugin-sdk` (npm)** | **Net new.** The published TypeScript package: generated wire types, the control/island registries, the React primitives (`ResolveNodes`, `useVitoAction`, `useVitoData`), and the build tooling that turns a plugin's React into a loadable bundle. |
| **The toolchain** | Scaffolding (`plugin:new`), the manifest format + signer, the contract/address snapshot gates, and the central-registry publish flow. |

> **The two faces of the SDK packages.** `vito/plugin-sdk` (PHP) and `@vito/plugin-sdk` (npm) are
> **author-side dev dependencies** — for IDE autocomplete, type checking, and the build. They are
> **never installed on the operator's server**: at runtime the PHP authoring classes ship *inside
> core Vito* (plugins `extend` them; see the Host API, §5.4), and the plugin's JavaScript is
> **pre-built** against the npm SDK and loaded as a static bundle. The operator's box needs no
> `composer` and no `node` — only the panel and the plugin archive (§9). This is what makes "a plugin
> is not a Composer package" workable without losing a typed authoring experience.

---

## 2. Vision

> **A Vito plugin is a self-contained, signed archive — from a central Vito registry, never
> Packagist — that declares what it needs, contributes pages and extensions through typed contracts,
> and, when it wants to, ships its own React. It installs without a `composer` binary on the server,
> disables from the UI with a flag, and cannot take the panel down when it breaks. Installing one is
> a consent dialog, not a leap of faith. Building one is `plugin:new`, not a fork.**

Three concrete bets define the vision:

- **The wire contract is generated, not written.** Every PHP schema DTO emits its TypeScript type.
  A plugin author imports `@vito/plugin-sdk` and gets exact, current types for every node, action,
  and field. A core change that breaks the contract is a *red build*, never a runtime `undefined`.
- **Rich UI is a published seam, available to everyone.** The same registry core uses for its
  `stats-dashboard` and `ssl-matcher` controls is exported from `@vito/plugin-sdk`. A plugin
  registers an island the identical way. "Plugins can't ship JS" stops being true.
- **Extension is an API, not a reach-in.** Pages publish *named, typed, versioned* extension points.
  Plugins target `SiteSettingsPage::SECTION_SLOT`, whose payload is a typed contract with a
  deprecation policy — not `after: 'details-card.php-version'`, a private id that can vanish in a
  refactor.

---

## 3. Design tenets

1. **Typed end-to-end or not at all.** Every boundary the plugin author touches is typed and the
   types are generated from one source of truth. No hand-synced `.d.ts`.
2. **Contracts are versioned; internals are not exposed.** Plugins bind to published interfaces and
   addresses with a semver + deprecation guarantee. They never receive the raw schema tree or core's
   internal node ids.
3. **The framework owns transport; the plugin owns intent; `app/Actions` own logic.** A plugin's
   PageAction validates and delegates to a domain action. The SDK never encourages business logic in
   a closure.
4. **Safety is structural, then declared, then isolated.** Scoping is enforced by `Binding`/tenancy
   (can't be forgotten); privilege is *declared* in a manifest the operator consents to; a failing
   plugin is *isolated* (it degrades its own surface, never the panel).
5. **The common case is trivial; the hard case is possible.** A CRUD page is a dozen fluent lines. A
   live terminal is an island you register. Nothing in between requires forking Vito.
6. **Every capability core has, a plugin has.** First-party pages and plugin pages are the *same
   shape*. If core needed a new node type or control seam, it ships in the SDK, not in a core-only
   private path.

---

## 4. Architecture overview

```
           ┌─────────── CENTRAL VITO REGISTRY (not Packagist) ───────────┐
           │  signed, versioned, self-contained archives (.vitopkg)       │
           └──────────────────────────────┬──────────────────────────────┘
                    download + verify signature/hash  (no composer, no git on the server)
                                          ▼
                         ┌──────────────────────── PLUGIN ARCHIVE (acme/vito-backups@1.4.0) ──────────────────┐
                         │  vito-plugin.json (manifest, signed)   vendor-scoped/ (deps, namespace-scoped)      │
                         │  src/                         resources/js/ (author-only)   dist/                   │
                         │   ├ BackupsPlugin.php          ├ islands/backup-timeline.tsx   (PRE-BUILT bundle +  │
                         │   ├ Pages/BackupsPage.php      └ controls/…                      integrity hash)    │
                         │   ├ Extensions/…  (typed)     plugin-autoload.php (flat classmap — no resolution)   │
                         │   └ database/migrations/                                                            │
                         └───────────────┬───────────────────────────────────────────────┬──────────────────────┘
                                         │ PHP: extends the HOST API                       │ JS: pre-built, lazy-loaded
                ┌────────────────────────▼───────────────────────┐         ┌──────────────▼─────────────────────┐
                │  Vito\Plugin\*  — SHIPPED INSIDE CORE VITO      │         │  host singleton @vito/plugin-sdk    │
                │   AbstractPage / Card / Entry / Table / Action  │  wire   │   generated wire types (·d.ts)      │
                │   Field DSL / DataEndpoint / Binding            │ ──JSON─▶│   registries: control + island      │
                │   ExtendPage / RegisterExtensionAction          │         │   primitives: ResolveNodes,         │
                │   HOST API: Contracts\Site|Server, Ssh facade   │         │     useVitoAction / useVitoData     │
                └────────────────────────┬───────────────────────┘         │   bundle loader / asset map         │
                                         │                                  └──────────────┬─────────────────────┘
   Vito's OWN lazy, guarded loader ┌─────▼──────────┐                                      │  registered at runtime
   (enabled-only; DB-flag gated;   │ PageRegistry   │  one catch-all route ─▶ PageController ─▶ dynamic/page renderer
    NOT Laravel auto-discovery) ──▶│ ExtensionReg.  │  (NO dynamic registration)           (resolves nodes via registries)
                                   └────────────────┘
```

The PHP side is largely the existing framework, **stabilized and extracted** into a versioned
package. The npm side is **net new** and is what makes the kit real. The four weak seams from
`critical_review.md` (§4, §6, §7, §1) are each closed by one component above: generated types, the
catch-all route, typed extension contracts, and the published island registry.

---

## 5. The PHP SDK (`vito/plugin-sdk`)

This is the existing authoring surface, promoted to a public contract. Almost nothing here is new
code; the work is **extraction, stabilization, and a compatibility guarantee**.

### 5.1 What the package exports

- **Lifecycle:** `PluginInterface` (`boot/enable/disable/install/uninstall/getName/getDescription`)
  — unchanged from `App\Plugins\PluginInterface`.
- **Registration builders:** `RegisterPage`, `ExtendPage`, `ExtendTable`, `RegisterExtensionAction`.
- **Page authoring:** `AbstractPage`, `AbstractArea` (referenced, not subclassed — areas are
  core-only), `Card`, `Page`, `Entry`, `DynamicTable`, `DynamicButton`, `DynamicDialog`,
  `DynamicAlert`, `DynamicLogView`, `DynamicCodeEditor`, `Control`.
- **Field DSL:** `Forms\{TextInput, Password, Textarea, Checkbox, Select, Repeater, Hidden, Control}`.
- **Behaviour:** `PageAction`, `DataEndpoint`, `Binding`.
- **Schema engine:** `SchemaNode` (contract), `EvaluationContext` (read-only; plugins receive it, do
  not construct it).
- **Extension contracts:** the typed extension-point interfaces published by core pages (§8).

### 5.2 What changes versus the current code

1. **Namespace.** Move the authoring surface from `App\Pages\*` / `App\Plugins\*` to `Vito\Plugin\*`,
   shipped **inside core Vito** at runtime and *also* published as the `vito/plugin-sdk` Composer
   package for **author-side dev use only** (IDE/types — never installed on the operator's server;
   see §1's two-faces note). Core ships it; plugins compile against it; the `App\Plugins\Interfaces`
   `class_alias` shim is retired at the SDK's 1.0.
2. **A frozen, documented `SchemaNode` wire grammar.** The node `type` strings (`card`, `card-row`,
   `table`, `dialog`, `control`, …) and their prop shapes become the package's published contract,
   with the generated TypeScript types as the machine-checkable mirror (§7).
3. **Stable extension points** replace ad-hoc address targeting (§8).

### 5.3 Authoring a page (unchanged ergonomics, now a public API)

```php
use Vito\Plugin\{AbstractPage, RegisterPage};
use Vito\Plugin\Components\{Page, Card, Entry, DynamicTable};
use Vito\Plugin\Forms\{Select, TextInput};
use Vito\Plugin\{PageAction, Binding};

final class BackupsPage extends AbstractPage
{
    public static function id(): string { return 'acme-backups'; }      // vendor-namespaced (§9.3)
    public function area(): AbstractArea { return app(SiteArea::class); }
    public function slug(): string { return 'backups'; }

    public function defaultAuthorize(): ?Closure
    {
        return fn (User $u, Site $s) => $u->can('update', $s);
    }

    public function schema(): array
    {
        return [
            Page::make('acme-backups')->title('Backups')->add(
                Card::make('acme-backups.config')->title('Configuration')->schema([
                    Entry::make('schedule')->label('Schedule')->state(fn (Site $s) => $s->backupSchedule())
                        ->action(PageAction::make('set-schedule')->patch()
                            ->form([Select::make('cron')->options(config('acme.schedules'))])
                            ->run(fn (Site $s, array $in) => app(SetBackupSchedule::class)->set($s, $in))),
                ]),
                DynamicTable::make('acme-backups.history')
                    ->table(BackupHistoryTable::class)
                    ->query(fn (Site $s) => $s->backups()),
            ),
        ];
    }
}

// in the plugin's boot():
RegisterPage::make(BackupsPage::class)->register();
```

The closure-DI engine, the IDOR-safe binds, the harvest of `set-schedule`, the `defaultAuthorize`
injection, the `tables:acme-backups.history` partial-reload prop — all provided by the framework. The
plugin author writes intent, namespaced under their vendor prefix.

### 5.4 The Host API surface — how plugins use `Site`, `Server`, `SSH`, …

A plugin is useless if it cannot read a `Site`, inspect a `Server`, or run an SSH command — yet
those are core's most volatile internals (`App\Models\Site`, `App\Helpers\SSH`) and its most
dangerous capabilities. So the SDK defines a deliberate **Host API**: the curated, versioned subset
of core that plugins are *allowed* to touch. Everything else in `App\*` is private and may change in
a minor release. Three rules govern it.

**1. The scoper excludes the host's namespaces — core is shared, never duplicated.**
The build-time scoper (§9.4) prefixes only the plugin's *bundled third-party* dependencies. The host
namespaces — `App\`, `Vito\`, `Illuminate\` — are on the scoper's **exclude list**, so a plugin's
reference to `Vito\Plugin\Contracts\Site` (or, where permitted, an `App\` class) is left untouched
and resolves to the **single running host copy** at runtime. There is exactly one `Site` class, one
`SSH` helper, one Eloquent — no duplication, no version skew, no two-Eloquents-in-one-process class
of bug. The scoper isolates the plugin's *own* deps; it never forks the host.

**2. Models are exposed through read contracts, not raw Eloquent.**
Plugins typehint **interfaces the SDK publishes** — `Vito\Plugin\Contracts\Site`,
`…\Server`, `…\Project` — which the concrete `App\Models\*` implement. The closure-DI engine still
injects the *real* model instance (so `fn (Site $site)` works), but the *type the plugin compiles
against* is the interface, exposing only the stable accessors core commits to
(`$site->domain`, `$site->path`, `$site->phpVersion()`, relationships as further contracts). This
bounds the compatibility surface to a hand-picked API rather than "every public method of an Eloquent
model" — core can refactor columns, traits, and internals freely as long as the contract holds, and
the deprecation cycle (§11) covers any contract change.

   - **Reads** go through the contract directly.
   - **Mutations** never call arbitrary model setters. A plugin changes state by delegating to a
     core Action (`app(UpdateWebDirectory::class)->update($site, $input)`) or its own
     `app/Actions`-style class — the same rule core pages follow. The contracts are intentionally
     read-biased; writes are funnelled through audited Actions.

**3. Privileged capabilities are SDK facades, gated by the manifest.**
`SSH` is the sharp example. A plugin does **not** reach for `App\Helpers\SSH` directly; it uses the
SDK facade:

```php
use Vito\Plugin\Ssh;

Ssh::on($server)->asUser('vito')->run(view('acme-backups::ssh.snapshot', [...]));   // Blade script, as today
```

`Vito\Plugin\Ssh` wraps the core `SSH` helper and exists so that one chokepoint can:
- **Enforce the declared capability** — a plugin that calls `Ssh::on()` without `"ssh"` in its
  `vito-plugin.json` capabilities is refused (and the operator was never asked to consent to it).
- **Audit** every plugin-originated SSH command with plugin attribution.
- **Preserve the house rules** — Blade-template scripts, sanitized inputs, run-as-target-user — as
  library defaults a plugin can't easily bypass (no `exec`/`shell_exec`; that ban extends to plugins).
- **Stay stable** — the facade signature is part of the versioned Host API even if the internal `SSH`
  helper is refactored.

The same pattern wraps the other privileged surfaces a plugin might want behind capability-gated SDK
facades: `Vito\Plugin\Storage` (server files), `Vito\Plugin\Http` (`outbound-http`),
`Vito\Plugin\Notifications`, and read access to secrets only through a masking helper that honours
the "never round-trip secrets, never log them" rules.

**The net:** what a plugin may use from core = the SDK's published contracts + capability-gated
facades, and that set **is** the PHP half of the compatibility surface in §11. Direct use of an
unexposed `App\*` class is a `plugin:lint` error (§12), so plugins can't quietly couple
themselves to core internals and break on the next upgrade.

### 5.5 How to build a page — the "page module" convention (core *and* plugins)

A guiding principle makes this section short: **core builds pages the exact way a plugin would** —
same `AbstractPage`, same components, same `RegisterPage`. The only difference is *where the module
ships* (core's in the app, a plugin's in its package). So one convention serves both, and the repo
already uses it (`app/Pages/SiteSettings/` is a page module today). Plugins are free to organize
differently, but matching it buys scaffolding, docs, and reviewer familiarity for free.

**A page is a self-contained folder — a "page module".** Answering the question directly: yes, roughly
your shape, with one important split (business logic vs presentation):

```
Server/Pages/Dashboard/                # one folder per page
├── DashboardPage.php                  # the AbstractPage: id() / area() / slug() / schema() / headless()
├── Components/                        # bespoke nodes for THIS page — AND Section subclasses (as the real SiteSettings module does)
│   ├── GraphComponent.php             # a custom component/Control (generic Card/Entry come from the SDK)
│   └── HealthSection.php              # a Section (rows()+headless()) — same folder, only promote to a Sections/ subfolder if a page accrues many
├── PageActions/                       # optional: extracted PageAction *definitions* — named so it can't be confused with app/Actions
│   └── RestartServer.php
└── Data/                              # optional: extracted DataEndpoint definitions
    └── RefreshGraph.php
```

**The one rule that matters: a page module is a *presentation* folder; business logic stays in the
shared domain layer.** Like a Filament Resource it describes UI — and, going one step further than
Filament, every mutation **delegates** to a domain Action rather than implementing logic inline. A
`RestartServer` that SSHes in and restarts services is reused across the page, the API, jobs and CLI, so
the *logic* lives in the shared domain layer and the page only describes transport and delegates:

```php
// CORE page — may call core's domain Actions directly:
PageAction::make('restart-server')->post()->confirm('Restart the server?')
    ->authorize(fn (User $u, Server $s) => $u->can('update', $s))
    ->run(fn (Server $s) => app(\App\Actions\Server\RestartServer::class)->handle($s));

// PLUGIN page — identical shape, but a plugin may NOT reference App\Actions\* (a §5.4 lint error);
// it delegates to its OWN action or a Host-API-exposed one:
    ->run(fn (Server $s) => app(\Acme\Backups\Actions\RestartServer::class)->handle($s));
```

**This is the one place the core/plugin symmetry is *not* symbol-for-symbol:** the page *shape* is
identical, but the **delegation target** differs — core reaches any `App\Actions\*`; a plugin reaches
only its own `src/Actions/*` or a Host-API-exposed Action (§5.4, §12). So `PageActions/RestartServer.php`,
if extracted at all, is a thin PageAction *definition*, never the SSH logic. **Decision rule:** reused
beyond this page → domain Action (shared); page-only glue → the `PageAction` (inline for simple cases, a
`PageActions/` class when a page has many). When unsure, push it to the domain layer — duplicating logic
into a page is the one thing to avoid (extract on shared *meaning*, not lookalike code).

| In the page module | Role |
|--------------------|------|
| `{Feature}Page.php` | The `AbstractPage` — declares area/slug and the `schema()` tree; harvests its actions/data. |
| `Components/*.php` | Bespoke `AbstractComponent`/`Control` nodes **and `Section` subclasses** for this page. Generic nodes (`Card`, `Entry`, `DynamicTable`) come from the SDK. |
| `PageActions/*.php` *(optional)* | Extracted `PageAction` definitions — transport only; they delegate to a domain Action. |
| `Data/*.php` *(optional)* | Extracted `DataEndpoint` definitions (log polling, async selects, a panel control's data feed). |
| **domain Actions — NOT in the module** | `app/Actions/{Domain}/` (core) / the plugin's own `src/Actions/*` (plugin). The reusable logic the page delegates to — a plugin must target its own Actions or a Host-API action, never `App\Actions\*` (§5.4). |
| `resources/js/…` | Custom React (the `GraphComponent`'s panel control), registered via the frontend SDK (§6). |

**Core** keeps page modules under `app/Pages/{Feature}/` today — group by area
(`app/Pages/{Area}/{Feature}/`) as the set grows — and registers them in `PagesServiceProvider`. **A
plugin** mirrors the layout in its package (`src/Pages/{Feature}/…`) and registers in
`PluginInterface::boot()` with `RegisterPage::make(DashboardPage::class)->register()` through Vito's
guarded, enabled-only loader (§9.2), not Laravel auto-discovery. **Scaffolding:** a `make:page Dashboard
--area=server` generator stamps the page + an empty `Components/`, leaving `PageActions/`/`Data/`
unstamped so they appear only when a page actually needs them.

**Worked example — your Dashboard, wired up** (`RestartServer`/`RefreshGraph` are illustrative names):
```php
final class DashboardPage extends AbstractPage
{
    public static function id(): string { return 'server-dashboard'; }
    public function area(): AbstractArea { return app(ServerArea::class); }
    public function slug(): string { return 'dashboard'; }

    public function schema(): array
    {
        return [
            Page::make('server-dashboard')->title('Dashboard')->add(
                Control::make('server-dashboard.graph')->using('server.dashboard.graph')   // ← GraphComponent's React panel control
                    ->with(fn (Server $s) => ['range' => '24h']),
                Entry::make('restart')->label('Server')->state(fn (Server $s) => $s->status->getText())
                    ->action($this->restartAction()),
            ),
        ];
    }

    public function headless(): array { return [ (new RefreshGraph)->endpoint() ]; }   // Data/RefreshGraph.php → a DataEndpoint
    private function restartAction(): PageAction { return (new RestartServer)->action(); } // PageActions/RestartServer.php → a PageAction def
}
```
`GraphComponent` is a `Components/` class emitting a `control` node backed by a registered React **panel
control**; `RefreshGraph` is a `Data/` `DataEndpoint` the panel polls (via the page's `data` map, the way
`log-view` does today) for fresh graph JSON; `RestartServer` is a `PageActions/` `PageAction` definition
that **delegates to a domain Action** — `App\Actions\Server\RestartServer` for *this core* page, or the
plugin's own `src/Actions/RestartServer` for a plugin page. The module shape is identical for core and
plugins; only the delegation target differs.

**Pitfalls (the convention's known costs):**
- **No page-module → page-module coupling.** A page must never call another page's `Components/` or
  `PageActions/`. Genuinely shared logic refactors *down* into a domain Action; cross-page UI extension
  goes through the sanctioned §8 extension points / hooks, never a reach-in. (Most important for plugins,
  who will be tempted to poke at core page modules.)
- **Don't let the shared domain layer become a dumping ground.** Keep Actions `{Domain}`-grouped and
  single-responsibility; "when unsure → domain" is a tie-breaker, not a licence to flatten everything.
- **Don't over-extract.** A three-row page needs no extracted `Section`/`PageAction`/`Data` classes —
  inline it; promote to a class on the Rule of Three.

---

## 6. The frontend SDK (`@vito/plugin-sdk`) — the net-new core of the kit

This is the deliverable that turns the framework into an SDK. It is a **published npm package** the
plugin's JavaScript depends on.

### 6.1 What it exports

```ts
// @vito/plugin-sdk
export * from './types';            // GENERATED wire types: SchemaNode, PageNode, CardNode,
                                    //   TableNode, DialogNode, ActionRef, DataRef, DynamicFieldConfig…
export { ResolveNodes, ResolveNode } from './renderer';      // render a node subtree
export { useVitoAction, useVitoData, usePageModels } from './hooks';   // dispatch actions / load data
export {
  registerFieldControl, registerPanelControl,
  registerRowActionsControl, registerCellComponent,           // the four seams (§6.3)
  registerIsland,                                             // free-standing rich UI (§6.4)
} from './registry';
export type {
  FieldControlProps, PanelControlProps, RowActionsControlProps, IslandProps,
} from './registry';
```

These are the *exact* primitives core uses internally today (`resolve-node.tsx`,
`pages/dynamic/controls/registry.ts`, `page-context.tsx`) — extracted, typed, and published.
The registries are already runtime-mutable `Map`s explicitly "by design, [for] plugin bundles"
(see the existing `controls/registry.ts` docblock); the SDK makes that promise executable.

### 6.2 How a plugin's JS loads (the bundle pipeline)

The missing half today: plugins cannot ship JS. The SDK adds the pipeline.

1. **Build.** The plugin's `resources/js` is built (Vite library mode) with `react`,
   `react/jsx-runtime`, and `@vito/plugin-sdk` declared as **externals**
   (`build.rollupOptions.external`) — they stay as bare `import` specifiers in the output, so the
   bundle ships *no* React and *no* SDK copy. Output is a hashed ESM bundle under `dist/`.
2. **Publish assets.** On install, `plugin:publish-assets` copies the bundle to
   `public/vendor/{plugin}/` and records its hash + entry path in the plugin row (alongside the
   existing `namespace` column).
3. **Load — and the singleton mechanism: an import map.** A bare `import … from '@vito/plugin-sdk'`
   in a pre-built plugin bundle resolves in the browser via an **import map** the host injects into
   the document `<head>` *before any module script runs*:
   ```html
   <script type="importmap">{ "imports": {
     "@vito/plugin-sdk": "/build/assets/vito-sdk-[hash].js",
     "react": "/build/assets/react-[hash].js",
     "react/jsx-runtime": "/build/assets/react-jsx-[hash].js"
   }}</script>
   ```
   > **Critical pitfall:** Vito's *own* app bundle must consume React **through the same import-map
   > entry** — not a separately-bundled copy — or the plugin gets a different React instance than the
   > app and you hit the classic "invalid hook call / two Reacts" crash. The single-instance promise
   > is real but it is *delivered by the import map*, not for free; an `es-module-shims` polyfill
   > covers any non-evergreen browser. (Module Federation is the heavier alternative; import maps are
   > chosen for being build-tool-agnostic and spec-native.)

   The host's bootstrap (which already knows the enabled-plugin set and a `bootstrap_version`) emits
   the manifest of `{plugin → assetUrl}`. The dynamic-page shell dynamically `import()`s each enabled
   plugin's entry **once**, before first render. The entry's side effect is registration:

   ```ts
   // acme-backups/resources/js/index.ts  (the bundle entry)
   import { registerIsland, registerPanelControl } from '@vito/plugin-sdk';
   import { BackupTimeline } from './islands/backup-timeline';
   registerIsland('acme-backups.timeline', BackupTimeline);
   ```
4. **Cache coherence.** Asset URLs are content-hashed; the enabled-plugin manifest is keyed by the
   existing `bootstrap_version`, which `InvalidatePluginState` already recomputes and broadcasts on
   every lifecycle change. So enabling a plugin invalidates the manifest and the new bundle loads on
   the next navigation — reusing the mechanism that's already there for catalogue data.

### 6.3 The four control seams (generalized, exported)

The schema references custom React by name; the SDK exports all four registration functions with
typed props (today's `FieldControlProps`/`PanelControlProps`/`RowActionsControlProps` plus the
inertia-table `registerCellComponent`):

| Seam | Register | Schema reference (PHP) | Use |
|------|----------|------------------------|-----|
| **Field** | `registerFieldControl` | `Field::component('name')` | custom/dependent/async form inputs |
| **Cell** | `registerCellComponent` | `Column->component('name')` | rich table cells |
| **Panel** | `registerPanelControl` | `Control::make()->using('name')` | whole dashboard regions |
| **Row actions** | `registerRowActionsControl` | `DynamicTable->rowActionsControl('name')` | bespoke per-row menus |

### 6.4 Islands (free-standing rich UI)

A control is bound to a schema node. An **island** is a free-standing rich component a plugin mounts
anywhere it controls — including inside its own panel control, or via a published page slot. Islands
are the home for terminals, live-tailing logs, charts, and graph editors. The contract:

```ts
export interface IslandProps {
  props: Record<string, unknown>;       // serialized ONCE on the server (no per-render reflection)
  models: { server?: ResourceRef; site?: ResourceRef };   // current area models, typed
  // streaming/realtime via the host's broadcast client, exposed as a hook:
}
registerIsland('acme-backups.timeline', BackupTimeline);
```

Island ↔ server traffic uses ordinary typed `DataEndpoint`s and the existing WebSocket broadcast
stack — there is no second transport to learn.

---

## 7. The wire contract — generated, not hand-written

The single biggest correctness upgrade over the current design (closes critique §4).

- **One source of truth: the PHP DTOs.** Each `SchemaNode` and `DynamicField` DTO is annotated for
  `spatie/laravel-typescript-transformer` (already a dependency — it currently emits enum unions
  only). The SDK extends the transformer's reach to **every node and field shape**.
- **Generated `types/` ship inside `@vito/plugin-sdk`.** A plugin importing the package gets the
  exact, current node/action/field/data types for the Vito version it depends on.
- **Drift is a build failure.** The existing `typescript:transform` drift-gate test (transform must
  be a no-op vs the committed output) is extended to the full contract and run in CI. A PHP prop
  added without regenerating types fails the build — it cannot reach a plugin as a runtime
  `undefined`.
- **Versioned with the SDK.** The types are published at the SDK's semver; a plugin pins
  `@vito/plugin-sdk@^1`, and a breaking wire change is a major bump with a migration note.

### 7.1 Keeping the SDK in lockstep with core (the generation pipeline)

> *"If core adds a field to the `Site` model, how does it reach the SDK automatically?"* — the
> defining maintenance question. The answer is **automatic generation, deliberate exposure, enforced
> drift**: the mechanical artifacts (PHP contracts, TS types, docs) are *generated*, never
> hand-written; *which* fields are public is one explicit decision; and CI *fails* if a generated
> artifact is stale. What you must never do is mirror the whole model — a raw column auto-flowing
> into the SDK would leak secrets/FKs/internal flags and turn every migration into a forever
> compatibility promise (the exact leak §5.4 exists to prevent).

**One source of truth per model: the public *projection*, not the columns.**
A model's public surface is an explicit, typed projection — reuse the existing API Resource
(`app/Http/Resources/*` already whitelist fields) or introduce a `spatie/laravel-data` object
(`SiteData`, `ServerData`). That projection is the single **deliberate-exposure point**: a column is
public **iff** it appears there. The same projection is what the model serializes onto the wire, so
the runtime payload and the published type are generated from one place and **cannot diverge**.

**One generator, three artifacts (`php artisan sdk:generate`).**
The command reads the projections + the backed enums (the transformer already does enums today) and
emits, from the same source:

1. the **PHP Host API contract** — `Vito\Plugin\Contracts\Site` (the interface plugins typehint, §5.4);
2. the **TypeScript type** in `@vito/plugin-sdk` (for islands/frontend);
3. the **SDK reference docs**.

Because core's `Site` is declared `class Site extends Model implements Contracts\Site`, PHP itself —
backed by Larastan at max level in CI — **structurally verifies the model still satisfies what the
SDK published**: remove or rename a still-exposed accessor and the model stops fulfilling the
interface, a red build, before any plugin ever sees it.

**Enforced drift, not hoped-for sync (`sdk:check`).**
CI regenerates and diffs against the committed artifacts — the identical pattern Vito already uses
for `typescript:transform` (no-op-or-fail) and `pages:ids --check`. A non-empty diff fails the
build. So you **cannot merge a change to a projection without regenerating**, and an *internal-only*
model change produces **no drift at all** (correct — it was never public). Sync is a gate, not a
discipline.

**Generation stays safe by semver (the BC diff gate).**
The generator also diffs the new contract against the last *published* one and classifies the change:
an **added** member ⇒ a minor bump; a **removed / renamed / retyped** member ⇒ a major bump. The gate
(PHP via `roave/backward-compatibility-check`, TS via an API-extractor report) refuses the merge
unless the SDK version + changelog match the classification — so auto-generation can never silently
ship a break to plugins. (Capability **facades** like `Ssh` are hand-authored — deliberately, since
they're low-churn privileged wrappers — but their signatures are captured in the same snapshot and
diffed the same way.)

**Worked example — adding `timezone` to `Site` (the question, end to end):**

```
1. Migration adds sites.timezone.                          # core schema change
2a. Internal-only? Do nothing. sdk:check stays green; nothing reaches the SDK.
2b. Should plugins see it? Add `timezone` to SiteData (the projection) — the ONE deliberate line.
3.  php artisan sdk:generate
       → Contracts\Site gains  public function timezone(): string
       → @vito/plugin-sdk  Site type gains  timezone: string
       → docs regenerate
4.  BC diff: a pure addition ⇒ minor. SDK 1.4 → 1.5; changelog entry written.
5.  CI: sdk:check (no drift) + Larastan (model satisfies the interface) + BC check → green.
6.  Plugins pinning ^1 receive `timezone` on their next dev-dep update; islands get the TS type.
```

The only human judgement in the loop is step 2 — *is this field public?* — expressed exactly once.
Everything after it is generated and gated.

### 7.2 Repository structure & the local-dev loop (a monorepo, symlinked for development)

§7.1 is only painless if a regenerated contract is visible to core's working tree **instantly** —
never via "publish the SDK, then `composer update` in Vito." Across many developers and branches that
round-trip is unworkable: two repos, two version lines, and a skew window every time someone adds a
field. The fix is structural — **put Vito core and both SDK packages in one monorepo, have developers
load the SDK from local disk, and publish only at release.**

```
vito/                                   # ONE repository
├── composer.json                       # the Vito app — path-repo → the PHP SDK
├── package.json                        # npm workspaces root
├── app/  resources/  …                 # Vito core
└── packages/
    ├── plugin-sdk-php/                  # composer pkg  "vito/plugin-sdk"   (namespace Vito\Plugin\*)
    │   ├── composer.json
    │   └── src/
    │       ├── Contracts/Site.php       # ← GENERATED here by sdk:generate
    │       └── …AbstractPage, Ssh facade, builders…
    └── plugin-sdk-js/                   # npm pkg  "@vito/plugin-sdk"
        ├── package.json
        └── src/
            ├── types/generated.d.ts     # ← GENERATED here
            └── …registries, ResolveNodes, hooks…
```

**PHP — a Composer *path repository* with symlink.** Vito's root `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "packages/plugin-sdk-php", "options": { "symlink": true } }
  ],
  "require": { "vito/plugin-sdk": "@dev" }
}
```

`vendor/vito/plugin-sdk` becomes a **symlink** to `packages/plugin-sdk-php`, so editing — or
regenerating — the SDK source is live in the very next request, with no `composer update`. Because the
SDK sits on the *same branch* as the core change, a PR that adds `sites.timezone` **and** regenerates
`Contracts\Site` is **one atomic commit**: nothing to bump cross-repo, no skew window, and a reviewer
sees the model change and the contract change together.

**JS — an npm/pnpm workspace.** Root `package.json`:

```json
{ "workspaces": ["packages/plugin-sdk-js"] }
```

Vito's frontend depends on `"@vito/plugin-sdk": "workspace:*"`; the workspace symlinks it into
`node_modules` and Vite resolves it from source. An IDE sees a regenerated `generated.d.ts` instantly.
Two honest caveats the live-edit loop has on the JS side that it doesn't on PHP: **(a) Vite does not
type-check** — it only transpiles — so a wire-type mismatch is a red build *only when `tsc --noEmit`
or CI runs*, never from the dev server alone; and **(b) the SDK package must be wired into the type
check** (its own `tsconfig`, added to the root `tsc` run / project references, plus `exports`/`types`
conditions pointing at *source* in dev and `dist` when published — the dual-package split). Editing the
SDK's *runtime* exports (registries/hooks, not just types) wants a `vite --force` restart to drop the
pre-bundle cache. So the PHP side is genuinely "edit and go"; the JS side is "edit, and the gate runs
in `tsc`/CI."

**The dev-vs-published split (the crucial part):**

- **Core developers** consume the SDK by **symlink / workspace** — live, versionless, branch-local.
  No publish and no update step *ever* during development. This is precisely the friction you flagged,
  removed.
- **Plugin authors** (outside the monorepo) consume the **published** `vito/plugin-sdk` (Composer) and
  `@vito/plugin-sdk` (npm), pinned to a version. The published artifact is the compatibility contract;
  the symlink is the development convenience — *same code, two consumption modes*.
- **Release is the only time anything publishes — and the PHP half needs a subtree split.** This is
  the one mechanic that is easy to get wrong: **public Packagist cannot publish a package from a
  subdirectory** — it references a whole VCS repo and serves dist zips of it. So you do **not** point
  Packagist at the monorepo (that would ship the entire, huge repo in every `composer require`).
  Instead, a release tag runs CI that (a) runs `sdk:check` + the BC-diff gate (§7.1), then (b)
  performs a **read-only git subtree split** of `packages/plugin-sdk-php` into its own dedicated repo
  (`vito/plugin-sdk`, the one registered on Packagist), propagating the tag — so the published dist
  contains *only* the SDK, a few KB, never the monorepo. This is exactly how **Symfony**
  (`symfony/symfony` → `symfony/console`), **Laravel** (`laravel/framework` → `illuminate/*`), and
  **Filament** publish; the established tools are **`splitsh/lite`** (the engine, needs full git
  history — `fetch-depth: 0`), wrapped by **`danharrin/monorepo-split-github-action`** (Filament uses
  it) and orchestrated by **`symfony/monorepo-builder`** (which syncs versions but does *not* itself
  push to Packagist — the split action does). The **JS half has no such limit** — npm publishes a
  workspace subdirectory directly (Changesets / `pnpm -r publish`), no split needed. ⚠️ Note: Private
  Packagist's "subdirectory package" feature does **not** help — its dist still ships the whole
  monorepo; only a real split produces a small package.
- **Registry division (state it plainly):** the **SDK** lives on **public Packagist** (via the split)
  because it's a dev dependency authors already trust; the **central Vito registry** (§9.1) is for the
  signed `.vitopkg` **plugin archives** only. "Never Packagist" in §2/§9 applies to *plugins*, not the
  SDK.
- **Production Vito** resolves the path package by **copy, not symlink** (`"symlink": false` in the
  release build, or the tagged version), so the deployed app physically contains
  `vendor/vito/plugin-sdk` — consistent with "the SDK ships inside core Vito" (§1). The operator's box
  still never runs Composer or Node.

**Net:** adding a field is an in-repo edit + `sdk:generate`; every developer on every branch sees
the new contract immediately because it is the *same working tree*; and the version/publish ceremony
happens once per release, gated, purely for the benefit of external plugins.

**Caveats (more than "minor," now that they're validated):**
- **Generated files are committed and generation must be deterministic** (stable member ordering, no
  timestamps/paths) — they are the reviewed compatibility contract and the input to `sdk:check`;
  non-deterministic output turns every cross-branch rebase into a conflict in `Contracts/*.php` /
  `generated.d.ts`.
- **`composer dump-autoload` is needed only** when adding a *new* PSR-4 prefix or `files`/classmap
  entry to the SDK (the repo's `optimize-autoloader: true` is Level-1, so *new classes under an
  existing prefix* still resolve via PSR-4 fallback — no dump). A `composer sdk:generate` script
  (generate + dump) hides this edge.
- **Under Octane**, regenerated PHP contracts need an **`octane:reload`/opcache reset** (long-lived
  workers cache compiled classes) — a non-issue under the default `artisan serve`. The real fiddly bit
  is **file watchers not following the `vendor/`→`packages/` symlink**: Octane-watch and Vite must be
  told to watch `packages/` explicitly. (The realpath cache is *not* a concern here — it only bites
  when a symlink's *target* is swapped between deploys, not when file contents change in place.)
- **Release hygiene:** the pipeline must assert it shipped a *copied* SDK (not a dangling symlink), and
  the two packages' versions must be bumped together (lockstep enforced in the release job).

### 7.3 The package graph — why the SDK stays *separate* from core (and the testbench folds in)

A natural question once `vito/core` exists: do we still need a separate `vito/plugin-sdk` — couldn't
the SDK just live inside core? **No — the dependency runs the other way, and that direction is the
whole point.**

```
vito/plugin-sdk    CONTRACT only: AbstractPage/Entry/PageAction/Binding/the form DSL, PluginInterface,
   ▲               Contracts\Site|Server (interfaces), the Ssh FACADE, the closure engine.
   │ implements /  No models, Actions, migrations, factories, or config. Tiny; tightly semver'd.
   │ binds
vito/core          IMPLEMENTATION: App\Models\* (which implement Contracts\*), the Actions, the
   ▲               framework runtime, policies, migrations, factories, config — and BINDS the Ssh
   │ boots         facade to the real App\Helpers\SSH. Huge; churns with the app.
   │
vito/core/Testing  Orchestra-style kernel boot + Vito's TestCase + SSH/Http fakes. Thin enough to live
  (the "testbench") as a component INSIDE vito/core — so the published set is TWO packages, not three.
```

**Honest concession first:** a separate SDK does **not** meaningfully reduce what a plugin author
downloads. Any plugin with feature tests pulls `vito/core` (via the testbench) into its dev/CI
environment regardless — "lighter author dependency" is true only for a pure unit suite running against
SDK fakes, not the real-world case. The separation earns its keep for two *other* reasons, both of
which survive that fact:

1. **Independent semver — the load-bearing reason.** The SDK carries its **own version line**. A plugin
   pins `vito/plugin-sdk: ^1` to declare "I depend on the contract," and core is then free to ship
   4.x → 5.x, refactor models, rename internals — the plugin keeps working as long as the contract
   stays `^1`. Put the contract *inside* `vito/core` and a plugin must pin `vito/core: ^4`; now every
   core major is a potential break and there is **no way to let a consumer depend on contract-stability
   independently of app-stability**. This is the §11 "survive upgrades" promise, and the exact,
   battle-tested precedent is **`symfony/*-contracts`** (and `psr/*`): a contracts package on its own
   slow version line, subtree-split from a monorepo, that the churning implementation declares it
   satisfies via Composer `provide`.
2. **An enforceable boundary.** A distinct package is what lets tooling assert "plugin *production* code
   may depend on `vito/plugin-sdk`, and must **not** touch `App\Models\*` or other core internals."
   The mechanical enforcer is **`composer-require-checker`**: because `vito/core` is `require-dev`, any
   use of an `App\*` symbol in shipped `src/` is by construction an *undeclared dependency* → a failed
   check (cleaner than the bespoke `plugin:lint`; `deptrac`/`phparkitect` express the same rule). One
   combined package erases the line — "used a contract" and "reached into an internal" become the same
   `use`.

**The distinction that actually matters is `require` vs `require-dev`, not "pulls core or not."** The
SDK is a **`require`** — the contract the plugin's *shipped* code compiles against (host-provided at
runtime, so excluded from the archive, §9.4). `vito/core`/the testbench is **`require-dev`** — present
in dev/CI to run tests, never shipped, never referenced by the plugin's runtime code, and free to float
(`*` or a CI version matrix). Both sit on disk during development, yes — but only the SDK is part of the
plugin's *declared, versioned runtime contract*. That holds even though `composer install` pulls
everything.

**On "circular dependency":** bundling the contract *into* core (one package) is not itself circular —
it's just big, and it forfeits the two benefits above. What *is* impossible is the SDK **depending on**
core (a genuine cycle, since core implements the SDK's contracts and binds its `Ssh` facade) — which is
why, given a separate contract, the direction is fixed at **`vito/core` → `vito/plugin-sdk`** (and
`testbench` → `core`). One implementation detail that preserves the no-cycle property: the SDK's `Ssh`
facade must resolve an **SDK-owned key/interface** (`Vito\Plugin\Contracts\Ssh`), never name
`App\Helpers\SSH` — core *binds* the concrete to that key (standard Laravel facade pattern). The
consolidation worth making is the *other* direction: fold the thin testbench into `vito/core` as a
`Vito\Core\Testing` component, leaving **two** published packages — `vito/plugin-sdk` (the versioned
contract a plugin `require`s) and `vito/core` (the implementation + test support a plugin `require-dev`s).

> **Biggest risk, stated plainly:** this all rests on `vito/core` being a **self-booting,
> composer-installable test host that owns the `App\` namespace** — and Orchestra Testbench exists
> precisely *because* a Laravel app isn't normally bootable as a dependency. Vito inverts the Testbench
> model (core boots itself rather than plugging into a fixture skeleton), and every supported core major
> must stay library-bootable, so this is a **per-major maintenance burden**, not a one-time extraction —
> the make-or-break engineering of the whole testbench story. (The runtime story is unaffected: the
> operator's app contains both packages, and a plugin archive excludes both from its scoped bundle, §9.4.)

---

## 8. Extension model — typed, versioned points (not string addresses)

Replaces address-into-internals targeting (closes critique §7). Two tiers:

### 8.1 Published extension points (structural — preferred)
A core (or plugin) page that *wants* to be extended **publishes a named, typed point**:

```php
// Owned & versioned by the page's author. The contract is the API.
interface ProvidesSiteSettingsSections
{
    /** @return array<int, SchemaNode>  @since 1.0 */
    public function siteSettingsSections(Site $site): array;
}
```

The page declares its extension points in metadata so they appear in `pages:ids` and the SDK docs:

```php
public function extensionPoints(): array
{
    return [ ExtensionPoint::slot('site-settings.sections', ProvidesSiteSettingsSections::class) ];
}
```

A plugin implements the interface; core collects implementations and renders them at the slot. When
core restructures, the *interface* changes under semver — static analysis flags every affected
plugin, instead of "append + warn" silently relocating a panel.

### 8.2 Address targeting (cosmetic — constrained)
`ExtendPage`'s `after`/`before` placement survives, but only against addresses the page **explicitly
publishes as stable anchors** (declared in `extensionPoints()`, snapshotted in `pages-ids.json`).
Targeting a *non-published* internal id is a lint error in the SDK's static-analysis pass, not a
silent runtime append. The current atomic-per-plugin rollback and "render failure never
auto-disables" semantics are retained as the runtime safety net.

### 8.3 The address/contract gate becomes the plugin compatibility gate
`pages:ids --check` already CI-gates every page/action/data/slot address. The SDK reframes that file
as the **published compatibility surface**: a core change that moves a *published* address/contract
is a deliberate, reviewed, semver-significant event. Internal ids are free to move.

### 8.4 Plugin hooks — typed action & decision points in core's flow
Pages/extensions let a plugin add *UI*. **Hooks** let a plugin participate in core's *runtime
flow* — run on deploy, veto a deploy, react to an event — at points core declares. The design is
drawn from a survey of WordPress (`do_action`/`apply_filters`), Symfony events, webpack `tapable`,
pytest `pluggy`, and Kubernetes admission webhooks; each piece below has a battle-tested precedent.

**Three kinds:**
- **Action hook** — side effects, no return. Core fires it; every registered listener runs.
  (≈ `do_action` / tapable `SyncHook`.)
- **Decision hook** — a boolean with a **default**; listeners vote; **any non-default vote wins**.
  (≈ Kubernetes *validating admission webhooks*: default-allow, any deny rejects.)
- **Value hook** — transform a value through listeners (a waterfall filter): e.g. rewrite a generated
  SSH script before it runs. (≈ `apply_filters` / tapable `SyncWaterfallHook`.) These are
  **string-keyed**, not class-per-hook, because the keyspace is open (one per script) — so they work
  differently from the two class-based kinds; see "Value hooks" below.

Action and decision hooks are the curated, class-based set; value hooks are the open, string-keyed set.

#### Action & decision hooks (class-based)

**A hook is a typed class in the SDK — with your static ergonomics on top.** Each action/decision hook
is its own class in `vito/plugin-sdk`, whose constructor carries the inputs as **SDK contracts only**.
The base class supplies the `register()` / `execute()` statics, so authoring reads exactly as you'd want:

```php
// vito/plugin-sdk:  packages/plugin-sdk-php/src/Hooks/{PreDeploy,ShouldDeploy}.php
final class PreDeploy extends ActionHook {                       // run-all, no return
    public function __construct(public readonly Site $site) {}   // Site = Vito\Plugin\Contracts\Site
}
final class ShouldDeploy extends DecisionHook {                  // boolean veto
    public function __construct(public readonly Site $site) {}
    protected bool $default = true;                              // proceed unless a plugin objects
}

// core fires them (core → sdk, allowed):
PreDeploy::execute($site);
if (! ShouldDeploy::execute($site)) { return; }                 // any plugin returning false blocks

// a plugin registers in PluginInterface::boot():
ShouldDeploy::register(fn (Site $site): ?bool => $site->hasPendingBackup() ? false : null);
PreDeploy::register(fn (Site $site) => app(MyPreflight::class)->run($site));
```

Backing the static sugar with a *typed class* (not an untyped WordPress-style positional facade) is
what keeps the signature reviewable and versioned — the signature **is** the contract.

**Decision semantics — precise, and order-independent.** A `DecisionHook` declares a `$default`;
listeners return **`bool` or `null` to abstain** (the `null`-abstains sentinel, as in `Gate::before` —
though we *collect-all and fold*, not short-circuit on first like `Gate::before` does):

```
result = default
for each listener:  r = listener(...)          // collect-all (don't short-circuit)
    if r === null: continue                    // abstain
    if r is not bool: r = abstain               // strict bool|null; a wrong type is treated as abstain
    if r !== default:  result = r              // any non-default (i.e. !default) vote wins
return result                                  // no listeners → default
```

For `ShouldDeploy` (default `true`): any `false` → `false`; all `true`/abstain → `true`; no plugins →
`true`. This is **AND-aggregation / unanimous "deny-overrides"** — exactly K8s validating webhooks (the
`default=true` polarity; `default=false` "any-true-wins" is the OR mirror, which has no K8s analogue).
**Order-independence holds *only because the result is strictly boolean*** (the one non-default value is
unique, so every winning write writes the same thing) — so `execute()` **enforces `bool|null`** and the
doc states it as a hard constraint, not an accident; a tri-state result would silently become
last-writer-wins and reintroduce the priority footgun. Decision listeners must be **cheap, pure
predicates**: we run *all* of them even after the outcome is settled (collect-all, for consistent
observation), so an expensive remote check in a predicate adds latency to the hot path — push expensive
work into an Action that runs *after* the gate.

**A veto should explain itself.** Like a K8s webhook's `status.message`, a `DecisionHook` lets a
listener return `Decision::deny('a backup is still running')` (sugar over `false` + a reason) so the UI
and audit log can show *which plugin blocked and why* — parity with the value-hook audit trail below,
and far better operator UX than a bare "deploy blocked".

**Failure isolation — fail-open by default (K8s `failurePolicy`).** Every listener runs inside the
§9.2 try/catch: a throwing or wrong-typed listener is logged (`PluginError`) and **abstains** — a
broken plugin can never crash core's flow or block it, consistent with Vito's "degrade, never crash"
stance. A security-critical hook may opt into **fail-closed** (`protected bool $safeValue = false`):
on a listener error, force the declared **safe value** (for a `ShouldDeploy` gate, `false` = block).
Note fail-closed is specified as "force the safe *constant*," **not** "force the non-default" — the
latter would force-*enable* a `default=false` feature when a plugin crashed, the opposite of safe. For
a `default=true` gate the two coincide; for a `default=false` hook the safe value *is* the default, so
opting into fail-closed there is a no-op (and `make:plugin-hook` rejects it).

#### Value hooks (string-keyed) — e.g. rewrite a generated SSH script

Action/decision hooks are *classes* because they're a small curated set. Value hooks are different: the
keyspace is **open and large** — one per Blade SSH template, say — so a class-per-hook doesn't fit. They
take the `apply_filters` shape (string key + threaded value), with three guardrails the naive version
lacks:

```php
// core renders a script, then lets plugins rewrite it before it runs:
$script = ValueHook::execute('core::ssh::'.$scriptPath, $script);   // e.g. core::ssh::services/webserver/nginx/install

// a plugin, in PluginInterface::boot():
ValueHook::register(
    'core::ssh::services/webserver/nginx/install',
    fn (string $script): string => str_replace('worker_connections 1024', 'worker_connections 4096', $script),
    priority: 0,
);
```

- **Namespaced keys → channels.** `{vendor}::{channel}::{id}`. A plugin **registers against** any
  channel it holds the capability for (e.g. core's `core::ssh::*`), and must vendor-prefix any channel
  it **defines/fires** itself (`acme::…`, the §9.5 rule). The `{vendor}::{channel}` prefix is the
  **channel**. (Value channels are the *only* §8.4 mechanism a plugin can use to fire a hook for *other*
  plugins — class-based hooks are core-authored only; see the registry note.)
- **Typed per channel, SDK types only.** A channel declares its value type once, in the SDK —
  `core::ssh` is `string → string` (a **non-nullable** type, see next bullet). The value is always an
  SDK type, never a core model, so the §5.4 boundary holds even though individual keys aren't classes.
  `execute()` **re-validates each filter's output against the channel type** (it does not trust the
  closure's hint) and returns the same type it received.
- **Waterfall reduction, ordered — and the PHP `null` trap.** Each filter receives the current value and
  returns the next, in `priority` then registration order, so the *second* plugin sees the *first*'s
  output. PHP has no `undefined`, so unlike tapable we can't use "returned nothing" as the pass-through
  sentinel: **`null` always means "pass through unchanged," value channels are non-nullable, and a
  filter that returns `null`/nothing is a no-op** (and `""`, `"0"` etc. are *real* values that replace —
  never treat falsiness as no-change). A **throwing or wrong-typed filter is skipped** and logged — one
  bad plugin can't corrupt the value or break the flow.

**Security of the `core::ssh::*` rewrite channel — the sharpest feature in the SDK.** It intercepts a
string that core then runs as **root** in a `sudo -u … bash` heredoc, raw. So the guardrails are
*constraints*, not preferences:

- **"No new power" is overstated — say what it really is.** A rewrite grants no new *primitive* (an
  `ssh`-capable plugin can already run root commands), but it confers **provenance-laundering** (code
  runs as *core*, not the plugin), **persistence** (fires whenever core re-runs the script, e.g. a
  re-provision long after install), and the ability to **undo core's own hardening** (strip a
  `ssl_protocols`/`chmod 600` line) — genuinely more than a plugin's own SSH call. That is *why* it's
  curated-tier, stated honestly.
- **Wholesale rewrite is curated-tier-only and disabled by default.** Community-tier plugins get
  **fragments only** (`{{ hook_fragment('core::ssh::nginx.install.after-config') }}` — additive at a
  named anchor, can't delete core's lines, doesn't waterfall over other fragments). Wholesale
  string-rewrite of a root script never reaches the unreviewed tier.
- **Per-channel/per-script capability, not a blanket `ssh:rewrite`.** The manifest declares
  `ssh:rewrite:core::ssh::services/webserver/nginx/*`, surfaced in the consent screen in plain language
  ("may rewrite the Nginx scripts that run as root"). A bare `ssh:rewrite` is a `plugin:lint` error.
- **Operator-previewed, hash-pinned diff *before* execution.** The audit (which plugin changed which
  script) is **forensic only** — an in-process plugin can tamper with logging — so the real control is:
  at install/enable, show the operator the baseline diff and pin it to the script's hash; a later silent
  re-mutation re-prompts. Core emits the audit event itself, capturing the *post-waterfall* string.
- **Core re-validates the post-waterfall string** before running it (re-assert run-as-target-user, reject
  embedded `exec`/`shell_exec`-style escalation) — the raw-string path must not bypass the §5.4 SSH
  house rules. Structured fragments are preferred precisely because they're validatable; raw rewrite is
  opaque.
- **Order is not a security control.** Two rewrites waterfall (plugin B can defeat plugin A's hardening),
  so at most **one rewrite per script outside the curated tier**, and a community filter never runs after
  a curated security filter.
- **Missing/renamed keys are caught, not silent.** A string key can't carry `@since` like a class, so a
  renamed core script would silently stop a filter firing — a *silent security regression*. Core
  therefore snapshots every fired SSH key into an **`ssh-channels.json`** (drift-gated by `sdk:check`,
  each key carrying `@since`/`@removed`); `plugin:lint` hard-errors on registration against an
  unknown/removed key and derives `requires.sdk` from it.

**Inputs MUST be SDK types — enforced three ways, the first for free.** A hook signature (or value-hook
channel type) may reference
only `Vito\Plugin\Contracts\*`, SDK enums/DTOs, and scalars — never `App\Models\*` or a core enum:
1. **Structural (free):** the hook class lives in `vito/plugin-sdk`, which does **not** require
   `vito/core`, so any `App\*` reference is an undeclared symbol → a `composer-require-checker` failure
   (§7.3's enforcer, now pointed at the SDK's own `src/`). A hook *cannot* see a core model.
2. **Generation gate:** `make:plugin-hook` refuses a non-SDK input type before it writes the file.
3. **Architecture rule:** a deptrac/phparkitect rule pins `Vito\Plugin\Hooks\*` to depend only on
   `Vito\Plugin\Contracts\*` + SDK DTOs (catches transitively-reachable-but-private types).

**Scaffolding.** `php artisan make:plugin-hook ShouldDeploy --decision --default=true` (or
`--action`) stamps a typed hook *class* into the SDK package from a stub — the `make:event` model,
but emitting into `vito/plugin-sdk` and rejecting core FQCNs in the signature. **Value hooks aren't
scaffolded per key** (the keyspace is open); instead the *channel* is declared once
(`--value-channel core::ssh --type=string --capability=ssh:rewrite --audited`), and core fires keys
within it freely (`ValueHook::execute('core::ssh::'.$path, $script)`).

**Class-based hooks are core-authored only.** Because a hook class must live in `vito/plugin-sdk` for
plugins to compile against it, and an external author has the SDK as a *read-only* dependency, only core
(in the monorepo) can define action/decision hook classes via `make:plugin-hook`. Plugins **listen** to
them. Plugin-to-plugin extension — one plugin firing a hook others handle — goes through the
**string-keyed value channels** (which need no shared class), vendor-prefixed and capability-gated. (This
is the one deliberate exception to the "every capability core has, a plugin has" symmetry and §8.1's
"core *or* plugin" extension points: class-based hooks are core-only; value channels are the plugin path.)

**Versioning & registry.** A class-based hook is "just another member" of the versioned SDK surface
(§7.1, §11): committed deterministic source; a **hook-catalogue snapshot** (sibling of `pages-ids.json`,
plus the `ssh-channels.json` for value keys) drift-gated by `sdk:check`; the BC-diff gate classifies
*adding* a hook as minor and *changing/removing* one as major; and a plugin's lint-derived `requires.sdk`
+ the install-time check refuse a plugin registering against a hook newer than the host's SDK (the VS
Code `engines.vscode` model). Two implementation realities the design must own:

- **The registry must be repopulated on every hook-firing boot pass — not just web requests.** This is
  the one ship-blocker. Hooks like `ShouldDeploy`/`PreDeploy` and the `core::ssh` rewrites fire inside a
  **queued job** (`DeployJob implements ShouldQueue`), and under the production `queue:work` daemon
  Laravel calls `forgetScopedInstances()` **before each job** while the `app->booted` callback that runs
  `BootPlugins` fires only **once per worker** — so a `scoped` `HookRegistry` populated only at boot is
  *empty for every job after the first* (a security veto that silently stops vetoing). Fix: keep the
  registry `scoped` (Octane-safe, mirrors `ExtensionRegistry`) but **re-run `BootPlugins` per unit of
  work** — `Queue::looping(fn () => app(BootPlugins::class)->handle())` plus scheduler/CLI equivalents —
  and make `BootPlugins` idempotent. "Per hook-firing boot pass (web + queue + scheduler + CLI)" is the
  correct scope, not "per request".
- **The SDK gains a small runtime + a container dependency.** The static `Hook::execute()`/`register()`
  resolve the scoped registry via `app(HookRegistry::class)` (so listener state is never a leaky static
  property). That means `vito/plugin-sdk` depends on `illuminate/container`/`support` and ships a
  registry + a `ServiceProvider` — so it is **not** as minimal as `symfony/*-contracts` (acceptable: the
  host provides `illuminate/*`, scoper-excluded — but stop implying parity). Binding follows the `Ssh`
  pattern (§7.3): the SDK declares the facade + an SDK-owned `HookRegistry` key; **core binds the
  concrete and owns population** — no cycle, since the SDK never names `App\*`. `execute()` iterates a
  snapshot of listeners so a hook fired from within a listener is re-entrancy-safe.

---

## 9. Plugin packaging, distribution, loading & lifecycle

> **A plugin is NOT a Composer package.** That is a deliberate, load-bearing decision, not an
> omission. Vito is self-hosted by operators who are not PHP developers and often have no shell
> habit; the install target ships with everything it needs and **no `composer` binary**. A
> Composer-distributed plugin would be auto-discovered and **force-loaded at kernel boot**, so a
> single throw in its service provider crashes `artisan` and every HTTP request *before any UI is
> reachable* — leaving the operator no recourse but to SSH in and hand-edit `composer.json`/`vendor/`.
> The model below keeps the current framework's genuine strengths — lazy loading, a DB enable/disable
> flag, and try/catch boot isolation — and builds the SDK on top of them.

### 9.1 Distribution — a central Vito registry (not Packagist)
Plugins are published to and installed from a **Vito-operated registry/marketplace**. They are
**not** exposed on Packagist and are never resolved by Composer on the operator's machine. The unit
of distribution is a **versioned, signed, self-contained archive**:

```
acme-vito-backups-1.4.0.vitopkg            (signed archive; expands to:)
├── vito-plugin.json         # manifest (§10): id, version, requires.vito, capabilities, assets, signature
├── src/
│   ├── BackupsPlugin.php     # implements Vito\Plugin\PluginInterface
│   ├── Pages/…  Extensions/…  Tables/…
│   └── database/migrations/
├── vendor-scoped/           # the plugin's PHP deps, NAMESPACE-SCOPED at build time (§9.4)
├── plugin-autoload.php      # a flat classmap for src/ + vendor-scoped/ (no resolution at runtime)
└── dist/                    # pre-built JS bundle + integrity hash (CI artifact; no npm on server)
```

Install = the panel calls the registry → downloads the archive → **verifies the signature and
hash** → extracts into the plugins directory (`app/Vito/Plugins/{vendor}/{name}/`, the existing
lazily-autoloaded path) → creates/updates the `Plugin` row → `migrate` → publish assets →
`InvalidatePluginState`. **No `composer`, no `git`, no dependency resolution, and no network beyond
the single archive download** ever touch the operator's server. (The existing "install from a
GitHub repo" path may remain as a secondary *sideload* option, but the registry is the primary,
signed channel.)

### 9.2 Loading & isolation — the property Composer would destroy
The SDK keeps Vito's **own** loader and hardens it; it never hands plugin code to Laravel's
auto-discovery.

1. **Enabled-only, lazy registration.** For each plugin whose `is_enabled` flag is true, Vito
   registers its `plugin-autoload.php` classmap (guarded by try/catch). Plugin classes load **only
   when Vito instantiates them** (`new $namespace`), never eagerly at kernel boot. A disabled plugin
   contributes *zero* loaded code.
2. **Enable/disable is a DB flag, evaluated before any plugin code is touched.** Disabling a
   misbehaving plugin from the UI leaves its files in place and the app fully up — you do **not** have
   to uninstall it, and there is no autoload dump or `composer.json` edit. This is the single most
   important behaviour Composer cannot provide.
3. **Failure isolation at every stage.** Autoload registration, `new $namespace`, and `boot()` are
   each wrapped — a parse error, a missing scoped dependency, or a thrown exception **auto-disables
   that one plugin** (`is_enabled = false` + a `PluginError`), exactly as `BootPlugins` already does
   for boot, and the rest of the panel is unaffected. At render time, `ExtensionRegistry`'s
   atomic-per-plugin rollback drops only the failing contribution, never the page.
4. **Safe-mode kill switch.** `VITO_PLUGINS_DISABLED=1` (or a flag file Vito writes automatically when
   it detects a boot-loop) makes the loader skip **all** plugins. Because plugin code is never in the
   force-loaded provider set, this always yields a bootable panel from which the operator can disable
   the culprit — **recovery without a shell**, which is the failure mode the operator actually faces.

> The contrast in one line: with the directory+flag model a broken plugin is **inert and
> disable-able from the UI**; as a Composer package it is **fatal and fixable only over SSH**.

### 9.3 Lifecycle (reuses the existing actions)
`install / enable / disable / uninstall` already exist (`App\Actions\Plugins\*`) and funnel through
`InvalidatePluginState`. With registry archives the steps are pure file + DB operations — no
package manager:

```
install  = download+verify archive → extract → migrate → publish-assets → InvalidatePluginState
enable   = is_enabled = true        → register loader → publish-assets (idempotent) → InvalidatePluginState
disable  = is_enabled = false       → InvalidatePluginState   (loader + asset manifest drop the plugin)
update   = download+verify new ver  → swap files → migrate → publish-assets → InvalidatePluginState
uninstall= rollback migs (policy)   → remove files + assets → delete row → InvalidatePluginState
```

`disable` and `uninstall` are distinct on purpose: disable is the instant, reversible safety valve;
uninstall removes files and (by policy) data.

### 9.4 Dependencies — vendored and scoped at build time (so the server needs nothing)
The one real reason to reach for Composer is dependency resolution — handled entirely on the
**author's** side, never the operator's. At build time the plugin's CI scopes its third-party deps so
their namespaces are prefixed (`Acme\Backups\Vendor\GuzzleHttp\…`), and ships the scoped
`vendor-scoped/` + a flat classmap inside the archive. Two tool-choice details that are easy to get
wrong:

- **Prefer Strauss over php-scoper for this exact job.** php-scoper is excellent at *prefixing* but
  notoriously **does not emit a Composer classmap** for the scoped tree — it leaves you to run
  `composer dump-autoload --classmap-authoritative` afterward, and "excluding namespaces can easily
  break Composer autoloading" is a documented php-scoper footgun. **Strauss** (and the older Mozart)
  were built for the WordPress "vendor-prefixed plugin" use case and *do* generate/merge the classmap
  — which is precisely the `plugin-autoload.php` the archive needs. So `plugin-autoload.php` is a
  **build step**, not free; pick a tool that produces it (Strauss) or add the explicit classmap dump.
- **The host's namespaces are *excluded*, not prefixed.** Whichever scoper, `Vito\`, `App\`, and
  `Illuminate\` go on the **`exclude-namespaces`** list (php-scoper's current directive — *not*
  `expose-namespaces`, which would alias/prefix them, the opposite of what's wanted). Excluded symbols
  are left untouched, so the plugin's `Vito\Plugin\Contracts\Site` reference resolves to the **single
  host copy** (§5.4). Only the plugin's *own* third-party deps are prefixed.

Consequences: **no version conflicts ever** (each plugin's deps under disjoint namespaces — *stronger*
isolation than Composer's single-resolved-version model) and **no resolution on the server** (the
classmap is static; no `composer` binary, no network, no half-written `vendor/`).

**Requiring the SDK without shipping it.** The plugin's `composer.json` does
`"require": { "vito/plugin-sdk": "^1" }` purely so the author gets `Vito\Plugin\*` for IDE + static
analysis. It is **not** packaged into the archive: `vito/` is on the scoper exclude list and the
`vendor/vito/` directory is dropped from the shipped classmap — the host provides the one runtime
copy. (This is the WordPress "a plugin never bundles core" pattern, and how `illuminate/*` deps are
treated as host-provided; do **not** use Composer `provide`/`replace` here — those are the *host's* to
declare, not the plugin's.) None of the author's Composer/Node toolchain is installed on, or required
by, the operator's server (see §6.2 and the two-faces note in §1).

### 9.5 Vendor namespacing (collision avoidance)
Every plugin-contributed id — page ids, node addresses, control/island names, route names — is
**required** to carry the vendor prefix (`acme-backups.*`, `acme.timeline`). The SDK's static-analysis
pass enforces it, so two plugins can't collide and a plugin can't shadow a core address. This makes
the framework's first-wins registry and address-collision rules predictable across an ecosystem.

### 9.6 Routing — one catch-all resolver (closes critique §6)
The SDK drops dynamic per-page route registration in favour of a **single catch-all** per area:

```
GET|POST  {areaPrefix}/{page}/{action?}     →  PageController@resolve
```

The controller resolves `(area, page, action)` from the URL against the live `PageRegistry`,
exactly as it already resolves `_page`/`_action` defaults — but now those identifiers live in the
*URL*, not in registered routes. Consequences:

- `RegisterPageRoutes`, the boot-time collision normalizer, and `refreshNameLookups()` are deleted.
- There is **no route cache to keep coherent** with the plugin set, and **no per-page Ziggy names**,
  so `GetZiggyRoutes::forgetCache()` and the "stale forever" hazard disappear from
  `InvalidatePluginState`. The frontend addresses actions by `(page, action)` id via `useVitoAction`,
  resolved against the `actions` prop map the server already ships — Ziggy is not in the loop.
- A disabled/uninstalled plugin's URL resolves to `null` in the registry → 404, same as today, with
  no stale cached route to clean up.

This is a *simplification* the SDK can make precisely because plugins address behaviour by id, not by
route name.

### 9.7 Plugin-owned migrations — yes, and here's how it actually works
A plugin ships ordinary Laravel migrations in `database/migrations/` inside its archive. (Note: this
*adds* a migrate step to the lifecycle — today's `install()`/`uninstall()` are bespoke hooks, not a
`migrate` call.) The mechanics:

- **Registration is explicit, not auto-discovered.** Consistent with §9.2 (Vito's own loader, never
  Laravel package discovery), the *lifecycle action* runs them — `migrate --path={plugin-dir}/database/migrations --realpath --force`
  — on install/update, recorded idempotently in the `migrations` table; the same path is registered
  into the test harness (§12) so `RefreshDatabase` picks them up. Nothing runs at kernel boot.
- **Class collisions are already gone; filename collisions are the real risk.** Laravel 13 migrations
  are **anonymous classes**, which removes the PHP *redeclaration* clash — but that is independent of
  de-duplication: the migrator keys a migration by its *filename* (the `migration` column), so two
  plugins shipping a same-named file would **silently skip the second**.
- **Fix it with vendor-prefixed filenames (primary), not a custom repository.** Require
  **lint-enforced vendor-prefixed migration filenames** (`…_acme_backups_create_settings_table.php`,
  the §9.5 rule extended) — stock Laravel, zero framework code, and since the plugin's *tables* are
  vendor-prefixed too, collisions are structurally impossible. A per-plugin `plugin_migrations`
  repository table is the *theoretical* alternative but is **not** "a few lines": the migrator's
  repository is bound at construction (no per-invocation `--table`/`setRepository()`), so it means
  forking the `migrate`/`migrate:rollback` commands — a small package's worth of surface (cf.
  `artesaos/migrator`). Treat it as a future option only if per-plugin run-isolation is ever needed.
- **Blast radius is conventional + consented, not sandboxed.** In-process, a migration *can* `DROP
  TABLE servers`; you can't hard-prevent it (the honest §10 framing). So: plugins create
  **vendor-prefixed tables** (`acme_backups_*`); `plugin:lint` flags any migration that references a
  core table; schema mutation is gated by a `db:schema` capability the operator consents to. Reviewed-
  tier plugins are audited for destructive DDL.
- **Uninstall favours the plugin's own teardown over `down()`.** Down-migrations rot; the
  `PluginInterface::uninstall()` hook is the reliable path for bespoke cleanup, with
  `migrate:rollback --path=…` as a fallback. Uninstall offers the operator a **"keep data / purge
  data"** choice — *disable* always keeps data (re-enable restores state); *uninstall* purges only on
  explicit consent. (The WordPress deactivate-keeps / delete-may-remove convention, made explicit.)
- **Failure isolation, with one MySQL caveat.** A throwing migration fails `install` → `PluginError`
  → the plugin stays disabled; the panel is untouched. The migrator logs per-*file*, so a re-install
  resumes from the failed file — **but MySQL has no transactional DDL** (Postgres does), so a *single*
  migration file with multiple DDL statements that fails midway can half-apply and become
  un-reinstallable. Mitigation: `plugin:lint` should push **one DDL change per migration file**.

So plugin migrations are viable on **stock Laravel** — the safe path needs no new framework code at
all (vendor-prefixed filenames); the custom-repository route exists but is a real fork, not a snippet.

---

## 10. Security & trust model

The honest framing (from `greenfield_approach.md` §3.7): an in-process PHP plugin has the operator's
full DB and SSH reach. The SDK does not pretend to sandbox arbitrary in-process PHP. It provides
**structure, declaration, isolation, and provenance**.

1. **Structural scoping (can't be forgotten).** `Binding` + project scoping make IDOR a property of
   the framework. A plugin's action that binds a row model gets the parent-ownership check for free;
   a plugin literally cannot query across projects through the framework's resolution path.
2. **Declared capability (consent).** `vito-plugin.json` enumerates the privileges the plugin uses —
   `ssh`, `db:write`, `outbound-http`, `runs-as-root`, `reads-secrets`. The install UI renders this as
   a consent screen. Capabilities are advisory for the in-process tier (PHP can't be hard-confined)
   but they are the *transparency* contract and they gate marketplace tiers.
3. **Provenance (signing + tiers).** Manifests are signed. A **curated tier** is reviewed and signed
   by Vito; community plugins install with a loud, explicit "unreviewed, full access" warning. The
   bundle's JS carries an integrity hash checked at load.
4. **Isolation (blast radius).** The non-Composer loading model (§9.2) is the backbone here: plugins
   are lazily, guardedly loaded — never auto-discovered — so a plugin that throws at *autoload* or
   *boot* auto-disables (`PluginError`) instead of crashing the kernel; one that throws at *render*
   drops only its own contribution (atomic per-plugin) and never the page; and the DB enable flag +
   safe-mode kill switch let an operator recover **from the UI, without a shell**. Island JS errors
   are caught at an SDK error boundary and degrade to a fallback, never a white screen.
5. **The untrusted tier is out-of-process (v2).** Genuinely untrusted code does not run as an
   in-process service provider. It ships a worker/CLI Vito talks to over a typed RPC boundary with an
   enforced capability allow-list. The SDK reserves this as the "v2 sandbox" and is explicit that the
   in-process tier is *trusted*, not sandboxed.
6. **Secret hygiene (SDK-enforced).** The SDK's `DataEndpoint`/API-resource helpers default to
   masking known secret fields and never expose internal filesystem paths — the house rules become
   library defaults so a plugin author can't trivially leak.

---

## 11. Versioning & compatibility guarantees

The promise the SDK makes concrete:

- **Semantic versioning on both packages.** `vito/plugin-sdk` (PHP) and `@vito/plugin-sdk` (npm) share
  a major version line. A plugin pins `^1`.
- **The compatibility surface is explicit:** the published node/field/action wire types, the
  `PluginInterface`, the registration builders, the **Host API** (the `Contracts\Site|Server|…`
  model interfaces + the capability-gated facades like `Ssh`, §5.4), the exported npm primitives, and
  the `pages-ids.json`-snapshotted **published** addresses/extension points. Everything else —
  including any `App\*` class not re-exposed through a contract — is internal and may change in a
  minor.
- **Deprecation cycle.** A contract slated for removal is marked `@deprecated` for one major cycle,
  emits a runtime/log warning when used, and is documented in an upgrade guide. Static analysis
  surfaces deprecated usage at the plugin's CI time.
- **Compatibility metadata — *derived*, not author-asserted.** `vito-plugin.json` declares
  `requires.vito` and `requires.sdk`; the installer refuses an incompatible plugin with a clear
  message instead of failing at boot. But install-time gating is only as good as a *correct* minimum,
  and a hand-typed one is the WordPress "Requires at least" footgun (declare too low, call a method the
  host lacks → hard runtime fatal). So **`plugin:lint` derives the minimum from the contract
  members the plugin actually references** — it typehints `Contracts\Site::timezone()`, that member
  carries `@since 1.5` (the BC snapshot, §7.1, records it), therefore lint asserts `requires.sdk >= 1.5`
  and **fails the build if the manifest under-declares**. The version-skew guarantee is mechanical, not
  a matter of author discipline.

---

## 12. Developer experience & tooling

The SDK is only real if building a plugin is pleasant.

- **Scaffolding:** `php artisan plugin:new acme/backups` generates the package skeleton
  (composer.json requiring — but not shipping — the SDK, a `PluginInterface` stub, an example page, an
  example island, a manifest, a test) **and the build recipe** — the single most error-silent author
  artifact: the CI config that runs the scoper with the correct `exclude-namespaces` (`Vito`/`App`/
  `Illuminate`) + classmap emission, Vite library mode with `react`/`@vito/plugin-sdk` externalized,
  and the sign step. Getting the exclude-list or the externals slightly wrong yields an archive that
  *passes locally* (where the host happens to provide everything) but breaks on a clean install — so
  scaffolding and linting this recipe matters more than scaffolding the page.
- **Local dev:** a path-repository workflow + `plugin:link` so an author develops against a real
  Vito install; the asset pipeline runs in watch mode.
- **Static analysis pass:** `plugin:lint` — enforces vendor-namespacing (§9.3), flags
  unpublished-address targeting (§8.2), flags deprecated-contract usage (§11), and checks the manifest
  schema.
- **Contract docs:** the generated wire types + the published extension-point interfaces are rendered
  into an SDK reference site, versioned per release.
- **Test kit (scope it honestly).** SDK-provided PHPUnit helpers (a fake area, a fake
  `EvaluationContext`, schema/wire assertions) and a Playwright island harness let a plugin test, *in
  isolation*, its **page's wire output**, its **island rendering** (against a mocked `useVitoData`),
  and **contract-conformance** (it compiles against `Contracts\Site`). What *cannot* be tested without
  a real host: anything hitting concrete models, `Ssh::on()`, real `DataEndpoint` queries, migrations,
  or `Binding` scoping — because the host isn't a Composer dependency, there's no Testbench-style
  bootable host (Filament plugins *can* do this via Orchestra Testbench; Vito plugins can't, by
  design). Integration testing therefore requires `plugin:link` against a real install — the SDK
  fakes prove your code is *shaped* right, not that it *works* against the real Eloquent/SSH.
- **Authoring tests (the harness question).** A plugin *should* ship its own suite, and can — the only
  new piece is *what it runs against*, because there's no Orchestra Testbench (the host isn't a Composer
  dep, by design). Two tiers:
  - **v1 — link into a dev Vito.** `plugin:link` symlinks the plugin into a development Vito
    checkout (`app/Vito/Plugins/…`) and registers its migration path; the plugin's PHPUnit suite then
    runs from the Vito root in Vito's own test context, inheriting the existing `TestCase`
    (`$this->user`/`$this->server`/`$this->site`), `SSH::fake()`, `Http::fake()`, `RefreshDatabase`
    (which now applies the plugin's migrations, §9.7), and the core factories. This reuses everything
    Vito's own tests use — no new infrastructure.
  - **scaling — the `Vito\Core\Testing` component shipped inside `vito/core`** (not a third package —
    §7.3). The instinct to "copy migrations + factories so they just work" is right about the
    *experience* and wrong about the *mechanism*: a factory returns a real `App\Models\Site`, which
    drags in its enums, relationships, the Actions a plugin delegates to, and the page framework — so a
    migrations-and-factories-only bench can't boot a feature test. Instead, `vito/core` itself (the
    `require-dev` dependency, split from `packages/`, version-locked per §7.2) carries the
    **contract-backed models + their factories + migrations + the framework** and ships an
    Orchestra-Testbench-style **`Vito\Core\Testing`** component (kernel boot + Vito's `TestCase` +
    `SSH`/`Http` fakes + a helper to register the plugin-under-test's own migrations). Its provider
    `loadMigrationsFrom(...)` so `RefreshDatabase` builds the schema **automatically**, and factories
    resolve by convention (`App\Models\Site` → `Database\Factories\SiteFactory`) so `Site::factory()`
    **just works** — provided core keeps models under `App\Models` and factories under
    `Database\Factories` (freeze that as part of the bench contract). `vito/core` is pulled from the
    same Composer registry as the SDK (the central `.vitopkg` registry is only for shipped plugin
    archives), which is what makes the version matrix below resolvable. A plugin's CI then runs
    `composer install && composer test` with no Vito clone. Three things to hold onto: **(a) depend,
    don't copy** — a copied snapshot reintroduces the drift the generated contracts exist to kill;
    depending on `vito/core` keeps one source of truth, gated by the same BC checks (§7.1). **(b) The
    bench's surface == the SDK's compatibility surface**, so a plugin integration-tests exactly what it
    may depend on; a call to a *non-contract* core Action can't be tested here — correct, since lint
    forbids that call (§5.4). **(c) This bounds only if plugins delegate to SDK-exposed Actions, not
    arbitrary `App\Actions\*`** — allowing arbitrary internal calls makes core's exposed-for-testing
    surface approach *all of core*. That tension is the real sizing decision, and it argues for
    promoting the Actions plugins are allowed to call into the Host API. **Cost, honestly:** this means
    productizing that slice of core as a composer-installable
    library (autoload `App\`, cleanly bootable providers) — migrations/factories are the easy 10%,
    bootable providers the 90% — so it's the *scaling* answer, built when the ecosystem justifies it;
    the `plugin:link` tier above needs none of it.
  - **What a plugin tests:** unit (its own Actions; `schema()` wire output via the fake
    `EvaluationContext`; validation rules); feature (the page renders `dynamic/page` with the right
    props; an action mutates — `assertDatabaseHas`; **IDOR returns 403** across a project boundary —
    the `Binding` guarantee; SSH paths via `SSH::fake()`); island rendering via the harness; and its
    *own* `pages:ids`/wire snapshots so it catches its own regressions.
  - **CI matrix.** The plugin runs its suite against the **range of Vito/SDK versions it declares**
    (`requires.sdk`), which is how that range stops being a guess (ties to §11's lint-derived floor).
- **Guardrails as DX:** `pages:ids --check` and the wire-snapshot pattern are exposed as SDK test
  helpers so a plugin can pin *its own* addresses and wire shape and catch its own regressions.

---

## 13. A complete worked example

**PHP — extend a core page through a typed point, and add a page with an island.**

```php
// src/BackupsPlugin.php
use Vito\Plugin\Contracts\Site;          // the Host API contract — NOT App\Models\Site (§5.4)
use Vito\Plugin\{Ssh, PluginInterface, RegisterPage, ExtendPage};
use Vito\Plugin\Components\Entry;

final class BackupsPlugin implements PluginInterface
{
    public function getName(): string { return 'Backups'; }
    public function getDescription(): string { return 'Scheduled site backups.'; }

    public function boot(): void
    {
        RegisterPage::make(BackupsPage::class)->register();          // G2: new page

        ExtendPage::make('site-settings')                            // G3: via published anchor
            ->addRow('site-settings.sections', fn (Site $s) =>
                Entry::make('acme.backups.summary')->label('Backups')
                    ->state(fn (Site $s) => $s->lastBackupAt()?->diffForHumans() ?? 'Never'))
            ->register();
    }
    public function enable(): void {}  public function disable(): void {}
    public function install(): void {} public function uninstall(): void {}
}

// somewhere in the plugin's own action (delegated to by a PageAction):
// Ssh::on($server)->asUser('vito')->run(view('acme-backups::ssh.snapshot', [...]));
//   ↑ capability-gated by "ssh" in vito-plugin.json; refused (and never consented to) otherwise.
```

```ts
// resources/js/index.ts (bundle entry — registers against the host SDK singleton)
import { registerIsland } from '@vito/plugin-sdk';
import { BackupTimeline } from './islands/backup-timeline';
registerIsland('acme.backups.timeline', BackupTimeline);
```

```tsx
// resources/js/islands/backup-timeline.tsx
import type { IslandProps } from '@vito/plugin-sdk';
import { useVitoData } from '@vito/plugin-sdk';

export function BackupTimeline({ props, models }: IslandProps) {
  const { data, loading } = useVitoData('acme-backups.timeline-feed', { site: models.site?.id });
  // …render a typed, streaming timeline. Errors caught by the SDK island error boundary.
}
```

```json
// vito-plugin.json
{
  "name": "acme/vito-backups",
  "requires": { "vito": "^4.2", "sdk": "^1" },
  "capabilities": ["ssh", "db:write"],
  "assets": { "entry": "dist/index.js" }
}
```

The plugin adds a page, extends a core page through a *published* anchor, ships a streaming React
island, declares its privileges for operator consent, and pins its compatibility range — entirely in
plugin-owned code, against versioned contracts, with no fork.

---

## 14. Roadmap / phasing

The SDK lands incrementally on top of the existing framework; each phase is independently valuable.

| Phase | Deliverable | Closes |
|-------|-------------|--------|
| **0 (done)** | Plugin Page Framework + the lazy, DB-flag-gated, try/catch loading model (the isolation backbone, §9.2) | the engine |
| **1** | Extract `vito/plugin-sdk` (PHP), freeze the `SchemaNode` grammar, define the **Host API** (`Contracts\Site|Server`, `Ssh`/other facades, §5.4), vendor-namespace enforcement | a stable PHP surface |
| **2** | Generate the **full** wire types via the transformer; extend the drift gate to the whole contract | critique §4 |
| **3** | Catch-all routing; remove dynamic registration + Ziggy coherence from the lifecycle | critique §6 |
| **4** | Publish `@vito/plugin-sdk` (npm): primitives + registries + types; the **bundle pipeline** + asset publishing | critique §1 (plugins ship JS) |
| **5** | Typed, versioned **extension points** + **plugin hooks** (action/decision, §8.4); constrain address targeting to published anchors | critique §7 |
| **6** | **Central registry** + signed `.vitopkg` archives + build-time dependency scoping + the safe-mode kill switch; manifest signing + capability consent + marketplace tiers; out-of-process untrusted tier (v2) | §9, §10 |
| **7** | Toolchain: scaffolding, lint, test kit, versioned contract docs | §12 |

Phase 4 is the inflection point: it is the moment "user-installable plugins with rich UI" stops being
a slogan and becomes a `npm i @vito/plugin-sdk`. Phase 6 is the *distribution* inflection — the
central registry and signed archives — but note the isolation it depends on already exists in Phase 0:
the directory-based, lazily-loaded, DB-flag-gated loader was never a Composer package, by design.

---

## 15. Summary

The Plugin Page Framework built the engine. The Plugin SDK builds the **kit and the guarantees**
around it: a generated, versioned wire contract so plugins can't silently break; a published frontend
package so plugins ship rich React as easily as core; typed, semver'd extension points so plugins
target an API and not core's internals; a catch-all router so the lifecycle has no cache-coherence
liability; and a manifest/trust model so installing a plugin is an act of informed consent. The PHP
authoring surface barely changes — it was already good. What changes is that, for the first time, you
can hand it to a stranger.
