# vito/core extraction — closure manifest (Phase 2)

Computed by auditing every planned-core subtree for `use` edges into the app stop-set
(`App\Actions|Jobs|Http|Console|Mail|Notifications|WebSocket|Tables|Sdk`). Reproduce with the grep in
`Phase 2` of the plan. This is the source of truth for what moves (A) and what must be refactored first (B).

## (A) Move to core (`packages/core/src`, FQCN-preserving)
Models, Enums, Contracts, Traits\*, DTOs, Data, Policies, Events, ValidationRules, Exceptions, SSH,
Facades, Helpers\*, ServerProviders\*, SourceControlProviders, DNSProviders, StorageProviders,
NotificationChannels\*, ServerFeatures, SiteFeatures, Tooling, Services (post-refactor), SiteTypes\*
(post-refactor), Plugins (+ relocated `Runtime/*`), GetBootstrap (FQCN unchanged), the workflow contract
trio (`WorkflowActionInterface`+`AbstractWorkflowAction`+`WorkflowActionDTO`). Plus database/migrations
(121), database/factories (40), database/seeders (13), core config, `resources/views/ssh` (+ core Blade),
core helpers from `app/Support/helpers.php`, and `src/Testing`.

## Stay in app
Actions, Jobs, Http, Console (kernel+commands), Mail, Notifications (concretes), WebSocket, Tables,
Sdk (generator), the WorkflowActions concretes + WorkflowServiceProvider, Auth/Route/Horizon/Demo
providers + a thin AppServiceProvider, both Kernels, `bootstrap/app.php`, app config overrides.
**Listeners STAY in app** (revision — see edge L below: they dispatch Jobs).

## (B) Cross-boundary edges to break in Phase 3 (core member → app), with resolution

### Action edges → move delegation to app callers / binding-seam
1. `Models\Server::checkConnection` → `Actions\Server\CheckConnection` — app caller.
2. `Models\Service` (start/stop/…) → `Actions\Service\Manage` — app caller.
3. `Models\BackupFile` → `Actions\Backup\ManageBackupFile` — app caller.
4. `Models\Metric` → `Actions\Server\BroadcastServerUpdate` — app caller.
5. `SiteTypes\AbstractProxiedSiteType::afterDeploy` → `Actions\Worker\CreateWorker` — move to deploy caller (`Site` uses `instanceof` only).
6. `Services\Webserver\{Nginx,Caddy}` → `Actions\Webserver\Generate{Nginx,Caddy}Config`, `Actions\Site\EnsureSiteVerificationKey` — **binding-seam** interfaces, app binds Actions.
7. `Services\Database\AbstractDatabase` → `Actions\Database\SyncDatabases` — binding-seam.
8. `Services\LogAnalysis\GoAccess` → `Actions\CronJob\DeleteCronJob`, `Actions\SiteStats\SyncGoAccessServer` — binding-seam.

### Http\Resources edges → app Observers/events (Resources stay in app)
9. `Models\ServerLog::created` → `Http\Resources\ServerLogResource` — app model Observer.
10. **`Traits\HandlesWorkerFailure` → `Http\Resources\*`** (NEW) — the trait is used by app-side worker
    flow; move the Resource use to an app Observer/caller, or keep this trait in the app (decide in Phase 3).
11. **`SiteTypes\AbstractSiteType` → `Http\Resources\*`** (NEW) — move the Resource construction to an app
    Observer/caller; the SiteType stays core, Action/Resource-free.

### Jobs edges
12. `Models\Site::deleting` → `Jobs\SSL\DeleteSiteSslJob` — app model Observer.
13. **`Listeners\HandleSiteDeletedStats`, `Listeners\HandleSiteCreatedStats` → `Jobs\Site\*`** (NEW) —
    these listeners wire events to business Jobs → **keep Listeners in the app** (revises the plan's
    "Events/Listeners → core": Events move to core, Listeners stay in app).

### Notifications edges
14. `Models\BackupFile` → `Notifications\FailedToDeleteBackupFileFromProvider` (concrete) — app Observer.
15. **`ServerProviders\{Hetzner,Vultr,AWS,Linode,DigitalOcean}` → `Notifications\FailedToDeleteServerFromProvider`**
    (NEW, concrete) — these fire a failure notification in the provider delete flow. Resolution
    (Phase 3 decision): either move the `FailedToDelete*` concrete notifications to core, or refactor the
    dispatch into an app-side caller/Observer. (Recommend: refactor dispatch to the app caller — keeps all
    `Notifications` concretes app-side, consistent with the boundary.)
16. `Helpers\Notifier`, `Facades\Notifier`, `NotificationChannels\*` → `Notifications\NotificationInterface`
    — **move `NotificationInterface` to a core contract**; concretes stay in app.

## Filesystem coupling (autoload-invariance does NOT cover — fix before moving)
- `Sdk\ProjectionRegistry`/`SdkPaths` glob `app/Data` → repoint to core (`Sdk` stays in app).
- `app/Support/helpers.php` → split into core vs app `files` (no function redeclared; `user()`/
  `plugins_path()`/key-pair helpers → core).
- `DemoServiceProvider` `scandir(app_path('Models'))` → Models move; fix the scan (Demo stays in app).
- `config/route-attributes.php` `app_path('Http/Controllers')` → stays app-side (base path = app in prod).
