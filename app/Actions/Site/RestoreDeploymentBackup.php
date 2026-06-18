<?php

namespace App\Actions\Site;

use App\Enums\DeploymentStatus;
use App\Jobs\Site\RestoreDeploymentBackupJob;
use App\Models\Database;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\StorageProvider;
use App\Services\Database\Database as DatabaseHandler;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestoreDeploymentBackup
{
    public function restore(Deployment $deployment): void
    {
        $this->validate($deployment);

        dispatch(new RestoreDeploymentBackupJob($deployment))->onQueue('ssh');
    }

    /**
     * Perform the restore over SSH. Runs inside the queued job.
     *
     * @throws Exception
     */
    public function execute(Deployment $deployment): void
    {
        $manifest = $deployment->backup_manifest;

        if (! $manifest) {
            throw new Exception('Deployment has no backup manifest.');
        }

        $site = $deployment->site;
        $server = $site->server;
        $root = $manifest['root'];
        $home = '/home/'.$server->getSshUser();

        /** @var StorageProvider $storage */
        $storage = StorageProvider::query()
            ->where('id', $manifest['storage_id'])
            ->where(function ($query) use ($server): void {
                $query->where('project_id', $server->project_id)->orWhereNull('project_id');
            })
            ->firstOrFail();

        $this->restoreDatabases($deployment, $manifest, $storage, $root, $home);
        $this->restoreFolders($deployment, $manifest, $storage, $root, $home);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function restoreDatabases(Deployment $deployment, array $manifest, StorageProvider $storage, string $root, string $home): void
    {
        $databases = $manifest['databases'] ?? [];

        if (empty($databases)) {
            return;
        }

        $server = $deployment->site->server;
        $service = $server->database();

        if (! $service) {
            return;
        }

        /** @var DatabaseHandler $handler */
        $handler = $service->handler();

        foreach ($databases as $entry) {
            /** @var ?Database $database */
            $database = Database::query()->where('server_id', $server->id)->find($entry['database_id']);

            if (! $database) {
                continue;
            }

            $slug = Str::beforeLast(basename($entry['file']), '.zip');
            $storage->provider()->ssh($server)->download($storage->path($root.'/'.$entry['file']), $home.'/'.$slug.'.zip');
            $handler->importDatabase($database->name, $slug);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function restoreFolders(Deployment $deployment, array $manifest, StorageProvider $storage, string $root, string $home): void
    {
        $folders = $manifest['folders'] ?? [];

        if (empty($folders)) {
            return;
        }

        $site = $deployment->site;
        $server = $site->server;
        $owner = $site->user.':'.$site->user;

        /** @var ?Deployment $activeRelease */
        $activeRelease = $site->deployments()->where('active', true)->whereNotNull('release')->first();

        foreach ($folders as $entry) {
            $target = $this->restoreTarget($entry, $site, $activeRelease);

            if ($target === null) {
                continue;
            }

            $temp = $home.'/deploy-restore-'.$deployment->id.'-'.basename($entry['file']);
            $storage->provider()->ssh($server)->download($storage->path($root.'/'.$entry['file']), $temp);

            try {
                $server->os()->extractArchiveAtomic($temp, $target, $owner);
            } finally {
                $server->os()->deleteFile($temp);
            }
        }
    }

    /**
     * Resolve and re-validate the restore target. Re-anchors release-relative folders
     * under the currently active release and rejects anything outside the site directory.
     *
     * @param  array<string, mixed>  $entry
     */
    private function restoreTarget(array $entry, Site $site, ?Deployment $activeRelease): ?string
    {
        $rel = $entry['rel_to_release'] ?? null;

        if ($rel !== null) {
            if (! $activeRelease || str_contains((string) $rel, '..')) {
                return null;
            }

            $target = rtrim($activeRelease->path().($rel === '' ? '' : '/'.$rel), '/');
        } else {
            $target = (string) ($entry['abs_path'] ?? '');
        }

        return $this->withinSite($target, $site) ? $target : null;
    }

    private function withinSite(string $target, Site $site): bool
    {
        if ($target === '' || str_contains($target, '..')) {
            return false;
        }

        $basePath = rtrim($site->basePath(), '/');

        return $target === $basePath || str_starts_with($target, $basePath.'/');
    }

    private function validate(Deployment $deployment): void
    {
        if (! $deployment->has_backups || ! $deployment->backup_manifest) {
            throw ValidationException::withMessages([
                'backup' => __('This deployment has no backups to restore.'),
            ]);
        }

        $inFlight = $deployment->site->deployments()
            ->where('status', DeploymentStatus::DEPLOYING)
            ->exists();

        if ($inFlight) {
            throw ValidationException::withMessages([
                'backup' => __('A deployment is currently in progress. Please wait for it to finish.'),
            ]);
        }
    }
}
