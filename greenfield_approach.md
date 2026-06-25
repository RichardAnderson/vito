# Greenfield Approach — Building Vito's Extensibility From Scratch

> Premise: we are building Vito again from zero. Same product (a self-hosted server-management
> panel), same end goals as the current plugin framework — **user-installable plugins**, **UI
> authored in PHP**, **plugins that add and extend pages** — but no obligation to preserve the
> existing Inertia/React app or any decision that led to a bespoke schema renderer.
>
> This is the document to spend the most thought on, so it starts from the goals rather than the
> code, names the one decision that determines everything else, and then designs each layer.

---

## 0. TL;DR — the recommendation in three sentences

Build the panel on **Livewire + Filament v4**, and make Vito's entire admin surface a Filament
panel. Plugins use the **Filament plugin registration API** but are **not** Composer packages and are
**not** Laravel-auto-discovered (that would be fatal-at-boot and SSH-only to recover — see §3.7 and
`plugin_sdk.md` §9); instead they ship as **signed, self-contained archives from a central Vito
registry**, loaded by **Vito's own lazy, DB-flag-gated loader**. They are scoped by Filament
**multi-tenancy** (= Vito projects) and authorized by **Policies** — so "UI builder in PHP," "add a
page," "extend a page," and "scope safely" are all *features we get*, not frameworks we write.
Reserve **React islands** for the genuinely rich, stateful
interactions (web terminal, live-streaming logs, charts) behind one typed mounting contract, and
expose that same island registry to plugins so a third party can ship rich UI too — the capability
the current design defers.

The rest of this document justifies that, designs it in detail, and gives the honest fallback if
"must stay React-first" is a hard constraint.

---

## 1. Separate the goals before designing

The current framework conflates several distinct goals into one mechanism, which is part of why it
grew so large. Greenfield, name them separately — they have different right answers:

| # | Goal | What "done" means |
|---|------|-------------------|
| **G1** | Installable third-party plugins | Discover, install, enable/disable, update, remove. Versioned. A trust/security story. |
| **G2** | Plugins add pages | A plugin contributes a new nav item + screen without patching core. |
| **G3** | Plugins extend existing pages | A plugin adds a field/section/action/column to a core page. |
| **G4** | Author UI in PHP | First-party features and plugins describe UI in PHP, not by hand-writing per-page React. |
| **G5** | Rich client interactions | Terminal, streaming logs, charts, dependent async forms. |
| **G6** | Safety | Project/tenant isolation (no IDOR), authorization, no secret leakage, blast-radius control for buggy plugins. |

The key insight: **G4 is a solved problem in the PHP world (Filament), G5 is a solved problem in
the JS world (React), and the architecture's whole job is to put the boundary between them in the
right place.** The current design drew that boundary in the worst spot — it rebuilt G4 in-house to
stay in the React world, then had to re-admit React (the "controls") for G5 anyway. Greenfield, put
G4 where it's free and G5 where it's free, and design one clean seam between them.

---

## 2. The decision that determines everything: server-driven vs client-driven

Every other choice falls out of this one.

**Option A — Server-driven core (Livewire/Filament) + React islands.** *(Recommended.)*
The 90% of Vito that is forms, tables, detail panels, settings, and CRUD is built with Filament,
which *is* a mature "UI builder in PHP." The 10% that is genuinely interactive is a React island
mounted into a Livewire view. Plugins are Filament plugins (+ optional islands).

**Option B — Client-driven core (React/Inertia) + a typed PHP→TS contract.**
Keep React as the rendering substrate (for brand/quality/team reasons), but if you do, commit to it
properly: a **generated** typed schema contract, a **published** frontend plugin SDK from day one,
and **no** dynamic route registration. This is "what the current project should have been if React
is non-negotiable."

I recommend **A**, and design it first and in most depth, because the stated goals (G2–G4) are
*literally Filament's feature list* and the stated weakness of the current design (G5 for plugins,
bespoke maintenance) is *exactly what A removes*. Section 8 designs B honestly for the case where
React is a hard requirement.

---

## 3. Option A in depth — Filament panel + React islands

### 3.1 Shape of the app

```
Vito (Laravel app)
├── Filament Panel  "app"                     ← the entire admin UI
│   ├── Resources: ServerResource, SiteResource, …      (G4: PHP UI builder, for free)
│   │   ├── Pages: List / View / Edit / custom pages     (G2 surface for core)
│   │   ├── Infolists / Forms / Tables / Actions         (Filament builders)
│   │   └── Relation managers (workers, cronjobs, domains, redirects…)
│   ├── Tenancy: Project = Filament tenant               (G6: isolation, for free)
│   └── Render hooks + plugin slots                      (G3 extension points)
├── Domain layer  app/Actions/*                          (unchanged philosophy: logic lives here)
├── React islands  resources/js/islands/*                (G5: terminal, log stream, charts)
│   └── mounted via <x-island> Blade/Livewire contract
└── Plugins  (signed archives from the central registry; Vito's own loader, not Composer)  (G1)
    └── each is a FilamentPlugin + optional islands + migrations
```

Filament gives us, **with zero bespoke framework code**, the exact things the current commit hand-built: an
infolist `Entry`, an `Action` with a modal+form, a `Table`, a `Select`/`Repeater` field DSL, closure
injection, and a plugin registration API. We delete the entire `app/Pages/*` subsystem from the
design before it's written.

### 3.2 G4 — UI in PHP, using Filament directly

The current `SiteSettingsPage` becomes a Filament resource page. The same intent, none of the
serializer/renderer/registry substrate:

```php
// Greenfield: a real Filament page, not a schema interpreted by a custom renderer.
class SiteSettings extends Page
{
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->record($this->site)->schema([
            TextEntry::make('domain')->url(fn (Site $s) => $s->getUrl()),
            TextEntry::make('php_version')
                ->suffixAction(
                    Action::make('updatePhpVersion')
                        ->form([Select::make('version')->options(fn (Server $srv) => $srv->installedPHPVersions())])
                        ->authorize(fn (Site $s) => auth()->user()->can('update', $s))
                        ->action(fn (array $data, Site $s) => app(UpdatePHPVersion::class)->update($s, $data)),
                ),
            // …
        ]);
    }
}
```

Closure injection, modal forms, validation→422, authorization — all native. There is no wire
JSON, no `EvaluationContext`, no `serialize()`, no React renderer, no `pages:ids` snapshot, no
dynamic route registration. Livewire holds the component state server-side; there is nothing to
serialize a closure *around*, so the entire "build model-free, evaluate later" contortion
disappears.

### 3.3 G6 — isolation and authz, structurally, for free

The single best idea in the current design — `Binding`, making IDOR structural — becomes a
*platform feature* instead of a custom primitive:

- **Project = Filament tenant.** Filament's multi-tenancy scopes every resource query to the
  current tenant and rejects cross-tenant record access at the framework level. The `Binding`
  parent-child check (`site.server_id === server.id`) becomes ordinary Laravel route-model binding
  + tenant scoping. No bespoke chain resolver.
- **Authorization = Policies**, enforced by Filament resource gates and `->authorize()` on
  actions. It is greppable (`SitePolicy::update`) rather than harvested out of a node tree — which
  directly fixes critique §8.

We keep the *philosophy* of the good idea and drop the *implementation* we'd otherwise maintain.

### 3.4 G2 — plugins add pages

A plugin is a registry archive (§3.7) whose `FilamentPlugin` class Vito's loader registers on the
panel. Adding a page is registering a resource or a custom page:

```php
class BackupsPlugin implements Plugin
{
    public function register(Panel $panel): void
    {
        $panel->resources([BackupResource::class])          // adds nav + CRUD pages
              ->pages([BackupDashboard::class]);
    }
    public function boot(Panel $panel): void {}
}
```

Loading is **Vito's own lazy, DB-flag-gated loader** (§3.7), **not** Laravel package auto-discovery —
for an *enabled* plugin, Vito's loader news-up the plugin class inside try/catch and lets it call
`Filament::registerPanel`/`->plugin()`. The `Plugin::register()` body above is Filament's API; the
*mechanism that reaches it* is Vito's controlled loader, so a broken plugin auto-disables instead of
crashing the kernel, and disable is a flag rather than an uninstall. Routes are Filament's; there is
no dynamic route registration and therefore no route-cache/Ziggy staleness problem to manage (fixes
critique §6).

### 3.5 G3 — plugins extend pages, via *typed contracts*, not string addresses

This is where I diverge from both the current design *and* naive Filament usage, because
extension-by-string-address (critique §7) is the fragile part. Two complementary mechanisms:

1. **Filament render hooks** for *positional* injection that is genuinely cosmetic
   (`PanelsRenderHook::PAGE_END`, etc.) — fine for "add a banner here."
2. **Explicit, versioned extension contracts** for anything structural. A core page that wants to
   be extensible *publishes an interface*:

   ```php
   interface ProvidesSiteSettingsSections   // owned & versioned by core
   {
       /** @return array<Component> */
       public function siteSettingsSections(Site $site): array;
   }
   ```

   Plugins implement it; core collects implementations and renders them. The contract is a typed
   PHP interface with a deprecation cycle — when core restructures, the *compiler/static analysis*
   tells the plugin, instead of "append + warn" silently relocating its panel. Extension points
   become a deliberate, documented, semver'd API surface, not an open invitation to reach into
   another team's node ids.

This costs a little up-front design (you must decide what's extensible) and buys durable
stability. It's the right trade for a plugin platform whose value depends on third parties not
breaking every minor release.

### 3.6 G5 — rich interactions as React islands (and the seam that makes plugins first-class)

Filament covers CRUD; it does not want to be a web terminal or a live-tailing log viewer. Those
stay React. The seam is one Blade/Livewire component:

```blade
<x-island name="log-stream" :props="['channel' => $site->logChannel(), 'follow' => true]" />
```

- `name` selects a registered React island; `props` are serialized **once** (no per-render
  reflection — fixes critique §5) and hydrated client-side.
- Islands are registered in a typed registry (`registerIsland('log-stream', LogStream)`), the same
  pattern as the current "controls" — but generalized and, crucially, **available to plugins on day
  one**. A plugin ships its island bundle; an asset-publish step (Filament already publishes plugin
  assets) makes it loadable. That delivers the capability the current design defers to "v2"
  (critique §1): a third party *can* ship rich UI.
- Island ↔ server communication is plain typed JSON endpoints (or Livewire events). Streaming uses
  the existing broadcast/WebSocket stack.

Result: **one** clearly-drawn boundary — CRUD/admin is PHP/Filament, rich/stateful is a React
island — instead of two co-equal UI paradigms bleeding into each other through four control seams
(fixes critique §3).

### 3.7 G1 — plugin distribution, lifecycle, and the security model

Vito is **self-hosted and single-tenant-per-install** (the operator owns the box). That trust
model is the most important fact for plugin security and it should be made explicit rather than
worked around:

> **⚠️ Correction (supersedes the bullet below).** An earlier draft proposed **Composer-package
> distribution with Laravel auto-discovery**. That is *wrong for Vito* and is retracted: an
> auto-discovered package is **force-loaded at kernel boot**, so one throw in its provider crashes
> `artisan` and every request before any UI loads — recoverable only over SSH — and `composer
> require` at runtime needs a binary + network the operator's box shouldn't require. The current
> framework deliberately keeps plugins **out** of Composer (git-cloned into `app/Vito/Plugins/`,
> lazily autoloaded, gated by an `is_enabled` DB flag, booted in try/catch), which gives
> **disable-without-uninstall** and **a broken plugin stays inert, not fatal**. Keep that. The
> corrected, authoritative distribution/loading/isolation model is **`plugin_sdk.md` §9** — a
> **central Vito registry (not Packagist)** shipping **self-contained, signed archives** with
> **build-time namespace-scoped dependencies**, loaded by **Vito's own lazy, guarded, DB-flag-gated
> loader** with a **safe-mode kill switch**. It applies whether the panel is Filament (Option A) or
> React (Option B): Filament's plugin *registration API* can still be used, but the *loading* must be
> Vito's controlled loader, never `php artisan package:discover`.

- ~~**Distribution: Composer packages**, from Packagist…~~ *(retracted — see the correction above and
  `plugin_sdk.md` §9).* Distribution is the **central Vito registry**; install =
  download + verify signature → extract → `migrate` → publish assets; enable/disable = a DB flag
  checked **before** the plugin's code is loaded; update = download + swap + `migrate`; uninstall =
  rollback-migrations policy + remove files. No `composer`/`git`/`node` on the operator's server.
- **The honest security truth:** an in-process PHP plugin has the operator's full DB and SSH
  reach. There is no real sandbox for arbitrary PHP in-process, and pretending otherwise is worse
  than naming it. So the security story is **trust + transparency**, not sandboxing:
  - A **signed manifest** (`vito-plugin.json`) declaring capabilities the plugin uses
    (`ssh`, `db:write`, `outbound-http`, `runs-as-root`), surfaced in the install UI so the
    operator consents to blast radius.
  - A **curated/reviewed registry** for the "blessed" tier; unsigned community plugins install
    with a loud warning.
  - **Per-plugin error isolation kept** (the current design's atomic-rollback + "render failure
    never auto-disables, boot failure does" is good and carries over).
  - For untrusted code specifically, the *real* sandbox is **out-of-process**: a plugin that wants
    to be untrusted ships a worker/CLI that Vito talks to over a typed RPC boundary, not an
    in-process service provider. Offer that as the "v2 untrusted tier"; don't pretend the
    in-process tier is sandboxed.

This is more honest than a framework that implies safety through structure while running plugin
code in-process with full privileges.

### 3.8 What Option A explicitly deletes from the current design

The entire `app/Pages/*` namespace, `PageController`, `RegisterPageRoutes`, `PageRegistry`,
`ExtensionRegistry`/`ExtensionActionRegistry`, `EvaluatesClosures`/`EvaluationContext`, the
`Dynamic*` component DTOs, the `resources/js/pages/dynamic/*` renderer, the four control
registries, the dialog-stack rewrite, `pages:ids` + the wire snapshot, and the dynamic-route cache
coherence in `InvalidatePluginState`. That's the ~11k-line substrate replaced by "use Filament +
one island contract."

---

## 4. The good ideas from the current design that survive (in Option A)

Greenfield is not a repudiation — several decisions were right and are kept, just relocated onto
platform features:

| Current design idea | Greenfield home |
|---------------------|-----------------|
| `Binding` structural IDOR safety | Filament tenancy + route-model binding + Policies |
| Logic delegated to `app/Actions/*` | Unchanged — Filament actions call domain actions |
| Per-plugin atomic failure / no auto-disable on render | Plugin boot isolation + render-hook guarding |
| Address/contract snapshot gate | Versioned PHP extension interfaces + static analysis |
| "Control" escape hatch for rich UI | The typed React **island** registry (now plugin-accessible) |
| Shell/data table partial reload | Native in Livewire/Filament tables |
| One `InvalidatePluginState` lifecycle tail | Mostly unnecessary (no dynamic routes/Ziggy); a thin cache-bust remains |

---

## 5. Concrete module layout (Option A)

```
app/
  Domain/                 # app/Actions/* — business logic (unchanged philosophy)
  Filament/
    Resources/            # ServerResource, SiteResource, … (CRUD + nav + pages)
    Pages/                # custom panel pages (dashboards, settings)
    Widgets/              # stat widgets
    Contracts/            # PUBLISHED extension interfaces (ProvidesSiteSettingsSections, …)
  Plugins/
    PluginManifest.php    # parse/validate vito-plugin.json + capabilities
    PluginManager.php     # install/enable/disable/update/remove orchestration
    Contracts/Plugin.php  # = Filament Plugin + Vito lifecycle hooks
  Policies/               # the authorization surface (greppable)
resources/js/islands/     # log-stream, terminal, charts, ssl-matcher — React, typed props
app/Vito/Plugins/         # installed plugin archives (lazily loaded by Vito; NOT Composer-managed)
storage/plugins/          # downloaded archives + published JS bundles
```

A first-party feature and a plugin are *the same shape* — a service provider that registers a
Filament plugin — so there is no "core page vs plugin page" duality (fixes critique §3 and the
"which world is this page in?" problem).

---

## 6. End-to-end example: a "Backups" plugin (Option A)

```php
// plugin archive: acme/vito-backups — loaded by VITO's loader (not Composer auto-discovery).
// Vito news-up BackupsPlugin inside try/catch only when the plugin is enabled.
class BackupsServiceProvider extends PluginServiceProvider   // a Vito SDK base, not a Composer one
{
    public function boot(): void
    {
        Filament::registerPlugin(new BackupsPlugin);     // Filament's registration API…
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // JS bundle was PRE-BUILT and copied to public/vendor/backups at install time (no node on server)
    }
}

class BackupsPlugin implements \App\Plugins\Contracts\Plugin
{
    public function manifest(): PluginManifest      // declares capabilities → install consent (G1/G6)
    {
        return PluginManifest::fromFile(__DIR__.'/../vito-plugin.json'); // uses: ssh, db:write
    }

    public function register(Panel $panel): void
    {
        $panel->resources([BackupResource::class]);       // G2: adds nav + pages
    }
}

// G3: extend the core Site Settings page via a PUBLISHED, versioned interface — not a string address
class BackupSettingsSection implements \App\Filament\Contracts\ProvidesSiteSettingsSections
{
    public function siteSettingsSections(Site $site): array
    {
        return [
            Section::make('Backups')->schema([
                Toggle::make('auto_backup')->afterStateUpdated(
                    fn (bool $v, Site $s) => app(ToggleAutoBackup::class)->set($s, $v),  // delegates to a domain action
                ),
                ViewField::make('history')->view('island', ['name' => 'backup-timeline', 'props' => [...]]), // G5 island
            ]),
        ];
    }
}
```

Everything the current framework needed ~80 PHP files and an 11k-line substrate to enable
(register a page, extend a page, author rich UI, scope it, authorize it) is here in plugin-owned
code on top of platform features. Authorization is a Policy, scoping is tenancy, the rich bit is a
typed island the plugin itself ships, and the extension point is a semver'd interface.

---

## 7. Option A — honest tradeoffs

No design is free; the costs of A are real and worth stating:

- **Filament lock-in.** You inherit Filament's release cadence and major-version upgrade pain, and
  its opinions about markup/UX. Mitigation: Filament is widely adopted, actively maintained, and
  *designed* for this; the lock-in is to a maintained platform, not a private one — strictly better
  than the current bus-factor-of-one framework.
- **Livewire learning curve + the React team.** If the team's strength is React, moving the CRUD
  surface to Livewire is a real skills shift. Mitigation: the React skill is now concentrated where
  it adds the most value (islands), not spread across every settings table.
- **Loss of pixel-control.** Filament's components are less freely styled than bespoke shadcn/React.
  Mitigation: Filament v4 theming + custom views cover most of it; the brand-critical screens can be
  custom Livewire/island pages.
- **Two runtimes still exist** (Livewire + React islands) — but the boundary is *one* explicit
  contract at a coarse granularity (a whole interactive widget), not four fine-grained seams woven
  through a shared renderer. Far less rub.
- **The island ↔ server protocol is net-new** and must be designed once (typed endpoints, auth,
  streaming). It's bounded and well-understood, unlike a general schema interpreter.

---

## 8. Option B — if React-first is a hard constraint

Suppose the team will not give up React/Inertia (legitimate: the existing UI is polished, the team
is a React team, the product brand is the React UI). Then build what the current project was
*reaching* for, but correctly. Keep server-driven schema, but fix the four things that make the
current version fragile:

1. **Generate the type contract end-to-end, don't hand-maintain it.** Every schema-node DTO emits
   its TypeScript type via the typescript-transformer (not just enums). The `.d.ts` is *generated
   and drift-gated*, so a PHP prop change that forgets the frontend is a **build error**, not a
   runtime `undefined` (fixes critique §4). The wire contract is the generated types, full stop.

2. **No dynamic route registration. One catch-all resolver.** A single route
   `…/{area}/{page}/{action?}` dispatched by a controller that looks the handler up from the
   registry. Page identity travels in the URL, not in per-page registered routes. This deletes
   `RegisterPageRoutes`, the collision normalizer, the name-lookup refresh, **and** the
   route-cache/Ziggy coherence protocol (fixes critique §6). Client-side, you address actions by
   `(page, action)` id, not Ziggy route names — so there's no Ziggy cache to invalidate.

3. **Ship the frontend plugin SDK on day one.** A published `@vito/plugin-sdk` npm package with the
   typed node contracts and a runtime registry for plugin React components, plus a build/asset
   pipeline so installed plugins load their bundles. This makes "installable plugins with rich UI"
   a *real* v1 capability rather than the deferred-to-v2 gap of critique §1. If plugins can't ship
   JS, a React-first plugin platform has no reason to exist.

4. **Typed, versioned extension points instead of string addresses.** Same as §3.5: a page
   publishes named, typed slots (`SiteSettingsPage::SECTION_SLOT`) with a documented payload
   contract and a deprecation cycle, not "reach into `details-card.php-version`." Positional address
   composition (`after: 'x'`) is allowed only for cosmetic, explicitly-stable anchors that are part
   of the published contract.

5. **Keep the genuinely good parts unchanged:** `Binding`/structural IDOR, delegation to
   `app/Actions/*`, per-plugin atomic extension rollback, the shell/data table split.

Option B is strictly *more* engineering than A (you maintain a renderer and an SDK), but if React
is mandatory it is the version that's actually safe and actually delivers G5-for-plugins. The
current design is best understood as **Option B with steps 1–4 unfinished** — generated types,
catch-all routing, the frontend SDK, and typed extension points are exactly its four weakest
seams.

---

## 9. Recommendation

| | Option A (Filament + islands) | Option B (React-first, done right) | Current design |
|---|---|---|---|
| Bespoke UI framework to maintain | **None** | A renderer + SDK | A renderer + 4 seams + dynamic router |
| G4 (UI in PHP) | Filament (mature) | Custom DSL | Custom DSL |
| G5 for **plugins** (rich UI) | Islands, day 1 | SDK, day 1 | **Deferred to v2** |
| G6 (isolation) | Tenancy + Policies | Binding (kept) | Binding |
| Routing complexity | Filament's | One catch-all | Dynamic registration + cache coherence |
| Type safety at the seam | N/A (server-rendered) | **Generated**, build-gated | Hand-maintained `.d.ts` |
| Extension stability | Versioned interfaces | Versioned slots | String addresses + append/warn |
| Biggest risk | Filament lock-in, Livewire reskill | Still maintaining a renderer | Bus factor, deferred payoff |

**Pick A.** The product's goals are Filament's feature list; building on it deletes the most code,
removes the maintenance and bus-factor risk (critique §2), draws one clean PHP/JS boundary instead
of two tangled paradigms (§3), and — by exposing islands to plugins immediately — actually ships
the headline capability the current approach defers (§1). Keep the genuinely good ideas (structural
scoping, action delegation, atomic plugin isolation) by mapping them onto platform features rather
than re-implementing them.

If, and only if, "the UI must remain React" is non-negotiable, build **Option B** — and treat the
current framework as a 70%-complete draft of it whose remaining 30% (generated types, catch-all
routing, the frontend plugin SDK, typed extension points) is precisely the work that makes it safe
and makes the plugin promise real.
