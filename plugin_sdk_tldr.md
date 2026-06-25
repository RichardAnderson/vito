# Vito Plugin SDK — TL;DR

> A kit that lets anyone build and extend Vito's UI **in PHP**, ship rich React only when needed, and
> install plugins safely **without touching a shell** — written against stable contracts so they
> survive Vito upgrades. (Full detail: `plugin_sdk.md`.)

## The vision
- Plugins add and extend Vito **pages** without forking core.
- The common case (forms, tables, settings) is authored in **PHP**; no JavaScript needed.
- The rich case (terminals, live logs, charts) ships as **React "islands."**
- Installing a plugin is a **consent dialog, not a leap of faith.**

## How plugins are built
- A page is a PHP **schema tree** → serialized to JSON → drawn by one generic React renderer.
- Rich UI = **islands**: pre-built React loaded via an **import map** so the host provides one React + one SDK (no "two Reacts").
- Plugins talk to core through a **Host API**: versioned interfaces (`Contracts\Site`) and capability-gated facades (`Ssh`) — never raw models.
- Business logic stays in core **Actions**; plugins delegate to them.

## How they're shipped and kept safe
- A plugin is **not** a Composer package — it's a **signed, self-contained archive** from a **central Vito registry** (not Packagist).
- Its PHP dependencies are **bundled and namespace-scoped at build time** (Strauss) — so the operator's server needs **no Composer and no Node**.
- Vito loads plugins with its **own lazy loader**, gated by a **DB enable flag** — *not* Laravel auto-discovery.
- A broken plugin **stays inert and is disabled from the UI**; a Composer package would crash the whole app and need SSH to fix.
- A **safe-mode switch** skips all plugins to recover without a shell.
- Plugins run **in-process with full access** — security is **declared capabilities + signing + consent + isolation**, not a sandbox (an out-of-process tier is reserved for untrusted code).

## How they stay compatible with core
- The wire types **and** PHP contracts are **generated from one source** and **drift-gated in CI** — never hand-maintained.
- Adding a field to a model = **one deliberate "make it public" line**, then regenerate; internal changes reach no one.
- Extension points are **typed and versioned**, not string addresses poked into core's internals.
- Each plugin declares the SDK version it needs; the linter **derives that minimum automatically** from what it uses.
- Scoping/IDOR safety is **structural** (one `Binding` primitive); permissions are real **Policies**.
- Routing uses **one catch-all resolver** — no dynamic route registration, no stale route caches.

## Packaging (the dependency shape)
- **Two PHP packages:** `vito/plugin-sdk` (the small, stable **contract**) and `vito/core` (the big **implementation** + test support).
- Direction is fixed: **`vito/core` depends on `vito/plugin-sdk`**, never the reverse.
- A plugin **`require`s** the SDK (its shipped code) and **`require-dev`s** core (for tests only); neither is shipped in the archive — the host provides them.
- One **monorepo**, symlinked locally so core devs need **no publish/update per change**; packages publish **only at release** (the SDK to Packagist via a subtree split).

## Plugins can also…
- **Hook into core's flow** — typed **action hooks** (run on deploy), **decision hooks** (veto a deploy; any "no" wins, with a safe default), and **value hooks** (rewrite a generated SSH script before it runs; capability-gated); all inputs are SDK types only.
- **Register migrations** (stock Laravel; vendor-prefixed filenames avoid clashes).
- **Write their own tests** — against a real Vito (linked dev install, or a Testbench-style component shipped inside `vito/core`).

## Status & risk
- Reviewed by multiple independent Opus agents; the architecture holds, with real precedent (`symfony/*-contracts`, Filament, WordPress, Testbench).
- **Biggest risk:** making `vito/core` a self-booting, installable test host is a per-major maintenance cost — the make-or-break piece.
- It builds on the existing Plugin Page Framework; the net-new work is the published frontend SDK, generated end-to-end types, the central registry, and typed extension points.
