# Critical Review — Plugin Page Framework

> A deliberately critical appraisal of the approach landed in `116b0141`. The goal here is not
> to relitigate decisions that were consciously made and validated, but to name the costs and
> risks honestly, because the commit message is "wip" and this is the moment to be skeptical.
> Strengths are noted at the end so the criticism is calibrated, not reflexive.

---

## 1. The headline capability is not actually delivered yet

The entire justification for this framework is **user-installable plugins that build rich UI**.
What ships is:

- Plugins can register **schema-only** pages (CRUD shapes: cards, rows, tables, dialogs).
- Anything richer — the SSL matcher, the GoAccess dashboard, the tooling panel, the worker
  action menus — required **first-party React "controls."** Every non-trivial migrated page
  needed one.
- Plugins **cannot ship JavaScript** (explicitly deferred to "v2").

Put those together: today an installable plugin can build a form-and-table page, but the moment
it needs any custom interaction it's stuck, because the escape hatch (a registered React control)
is only available to core. So the system delivers "plugins can build the easy 70% of a page,"
which is precisely the part that was never the hard problem. The hard problem — *third parties
shipping rich, safe UI* — is still ahead, and the schema indirection is a tax paid now against a
benefit that hasn't arrived.

Meanwhile the user-visible result of ~11k lines is **the same pages behaving the same way**. The
wire-snapshot test exists to *prove* nothing changed. That's correct for a migration, but it
means the payoff is entirely deferred and entirely contingent on the plugin ecosystem
materializing.

## 2. This is a bespoke reimplementation of Filament

The authoring layer was explicitly chased toward "Filament-fluent." And it got there — but by
hand-building Filament's greatest hits:

| Built here | Filament equivalent (mature, battle-tested) |
|------------|---------------------------------------------|
| `EvaluatesClosures` reflection DI | Filament's `$get`/`$set`/injected-utility resolution |
| `Entry` infolist row | `Infolists\Components\TextEntry` |
| `PageAction` + modal | `Filament\Actions\Action` |
| `DynamicTable` | `Filament\Tables\Table` |
| `Field`/`Select`/`Repeater` DSL | `Filament\Forms\Components\*` |
| `ExtensionRegistry` / slots | Filament plugin `->register()` + render hooks |
| schema serialization + renderer | Livewire (no serializer needed) |

The team now owns a private UI framework — a form builder, an infolist, a table wrapper, a
closure DI engine, a plugin registry, a dynamic router, and a React renderer for all of it —
forever. Every one of those is a thing Filament already maintains and a thing that now has a bus
factor of one. The reason Filament wasn't *used* is that Vito is Inertia/React, not
Livewire/Blade — which is a legitimate constraint, but it means the cost of the React decision is
"rebuild Filament," and that cost is being paid quietly under a "wip" commit.

## 3. Two UI paradigms now coexist permanently

Vito is an Inertia + React app where you write a page as a React component. It is now *also* an
app where you write a page as a PHP schema tree rendered by a generic interpreter. A contributor
must know which world a given screen lives in, and the rules differ: data flow, where
authorization lives, how to add a field, how to debug. New screens face a "normal React page or
schema page?" decision with no obvious default. This is durable cognitive overhead, and the
boundary (the four control seams) is exactly where the two worlds rub and produce the subtlest
bugs.

## 4. The type-safety story is weaker than it looks

The wire is `array<string, mixed>` JSON. The frontend node contracts in `dynamic-page.d.ts` are
**hand-maintained** and can silently drift from the PHP component DTOs that produce them — the one
thing TypeScript was supposed to prevent at this boundary. The mitigations are real but narrow:

- The typescript-transformer covers **enums only**, not node shapes.
- The wire-snapshot test pins **one page** (`site-settings`) structurally, not the contract.
- `pages:ids` pins **addresses**, not payload schemas.

So a component that adds a prop on the PHP side and forgets the `.d.ts` produces a runtime
`undefined`, not a compile error — the failure mode of a stringly-typed system, which is what
this is at the seam.

## 5. Closures-everywhere has real, recurring costs

The "build model-free, evaluate later" architecture exists *because* closures can't hold
serializable state. That single fact ripples outward:

- Route actions must be **static** (`[PageController, 'show']` + route defaults) because closures
  break `route:cache`. The handler indirection through `_page`/`_action` defaults is overhead the
  framework pays to stay cacheable.
- Every closure is resolved by **reflection on every render** (name-then-type fallback). That's a
  per-request cost and, worse, a class of **runtime** errors ("Unable to resolve page closure
  parameter `$foo`") that a typed signature would have caught at author time.
- Debugging is opaque: a value materializes from a reflection-driven DI lookup with a
  default/null fallback chain. There is no stack you can read to see *why* `$server` resolved to
  what it did.

## 6. Dynamic routing is a fragile, high-coordination mechanism

Routing strategy (a) registers real routes from the live registry at boot, every non-cached
request, with collision normalization and name-lookup refresh — and then must keep **two caches**
(route cache + Ziggy client script) coherent with plugin lifecycle, or "client `route()` lookups
go stale forever" (the team's own words). `InvalidatePluginState` is essentially a
manually-maintained cache-coherence protocol. Every future change to how routes or Ziggy are
cached is a chance to reintroduce the stale-forever bug. The simpler alternative the design
considered — a single catch-all resolver (strategy b) — was rejected partly to keep per-page
Ziggy names, which is the very thing that creates the coherence burden. The convenience bought a
standing liability.

## 7. Extension-by-address is structurally brittle

Plugins extend pages by **string addresses** into another author's node tree
(`details-card.php-version`, `after: 'php-version'`). That couples a third party to core's
*internal layout*. The policies around this — "missing targets append + warn," "first-wins
replace," atomic-per-plugin rollback — are all mechanisms for *coping with* the brittleness, not
removing it. `pages:ids` protects core from breaking its own addresses, but it does nothing for a
published plugin pinned to an address that core legitimately renames: that plugin's panel silently
relocates to the page bottom (append + warn) and no one notices until a user complains. Positional
composition against private structure is a known-fragile pattern; this inherits all of it.

## 8. Authorization moved from greppable to emergent

The old controllers had explicit `$this->authorize(...)` calls — a reviewer greps for them. Now
the write gate is **harvested**: an action's `authorize` is either declared on the node or
**injected** from `defaultAuthorize()` during `allActions()`, and an action with no gate is simply
**not routed**. This is clever and mostly safe, but the security-critical invariant now depends on
the harvest correctly bubbling every action up the tree, the injection running, and authors not
mis-filing a mutation in `headless()` without a gate. A node that fails to surface its action, or a
data endpoint mis-marked `->public()`, is a silent gap rather than a missing-line a human notices.
Security that depends on data-flow through a tree walk is harder to audit than security that's a
visible statement at the top of a method.

## 9. The migration's success metric was "change nothing"

Enormous effort went into byte-identical equivalence: the wire snapshot, preserving exact route
names with zero `->routeName()` calls, and — notably — **deliberately preserving latent bugs**
(the Statistics row that never renders because `services['log_analysis']` indexes a HasMany
collection by string key). Preserving a known bug to keep a fingerprint stable is a defensible
migration tactic, but it's a signal worth sitting with: the project spent its energy *proving it
built the same thing*, and shipped some of the old thing's defects forward by design. The
refactor's value is real only if you believe the plugin future; judged on present user value, it's
a lateral move with new moving parts.

## 10. The verification gap is large for a UI-justified project

The entire premise is UI, yet the frontend is **tsc-verified only** — the renderer, the four
control seams, the dialog-stack rewrite, and ~7 migrated pages have not been run in a browser in
this work. The build relies on the user to do visual verification after the fact. For a change
whose whole point is presentation, "the types compile" is a thin guarantee, and several
divergences are already flagged as unverified (row-action visibility edge cases, processing-row
spinners, the degraded branch/source-control selects).

## 11. "Write LESS code" is not obviously satisfied

`SiteSettingsPage` is 125 lines **plus** eight section classes **plus** the framework. The old
`SiteSettingController` was 353 self-contained lines. For first-party pages, the framework trades a
fat controller for thin-but-many files and a large shared substrate; total code and indirection go
*up*. The "less code" thesis only pays off if many *plugins* reuse the substrate — again, a
deferred, ecosystem-contingent bet.

---

## What is genuinely good (so this stays honest)

- **`Binding` / structural scoping is the best idea here.** Making IDOR a property of the
  framework rather than a check each handler remembers is a real safety win, and reusing one
  primitive across areas/actions/data/extension-actions is clean.
- **Delegating all business logic to `app/Actions/*`** keeps the framework about transport. The
  pages are genuinely thin over the existing domain layer; the logic wasn't duplicated.
- **The discipline around contracts is above average.** The address snapshot gate, the wire
  fingerprint, the enum transformer, atomic-per-plugin extension rollback, and "render failure
  never auto-disables" are all thoughtful, production-minded choices.
- **The shell/data table split** (closure-valued `tables:{id}` siblings for partial reload) is a
  neat solution to realtime-without-rebuilding.
- **The lifecycle refactor** (one `InvalidatePluginState` tail instead of four copies) is a
  straightforward correctness improvement independent of the rest.

The criticism above is mostly *strategic* — the engineering inside the chosen frame is careful.
The question the "wip" state invites is whether the **frame itself** (rebuild Filament on top of
React, defer the actual plugin-JS capability, accept a bespoke framework's permanent maintenance)
was the right one. The next document argues what I'd do instead with a clean slate.
