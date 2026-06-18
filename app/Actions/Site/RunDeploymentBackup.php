<?php

namespace App\Actions\Site;

use App\Models\Database;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\StorageProvider;
use App\Services\Database\Database as DatabaseHandler;
use Exception;
use Illuminate\Support\Str;
use Throwable;

class RunDeploymentBackup
{
    /**
     * Capture configured folders + databases for a site BEFORE its deployment runs.
     * A failure here (including a missing storage provider) aborts the deployment.
     *
     * @throws Exception
     */
    public function run(Deployment $deployment): void
    {
        $site = $deployment->site;
        $config = $site->deploymentBackup;

        if (! $config || ! $config->enabled) {
            return;
        }

        $storage = $config->storage;

        if (! $storage) {
            throw new Exception('Deployment backup is enabled but no storage provider is configured.');
        }

        $server = $site->server;
        $home = '/home/'.$server->getSshUser();
        $root = "deploy/{$site->id}/{$deployment->id}";

        /** @var array<int, string> $uploaded */
        $uploaded = [];
        $manifest = [
            'storage_id' => $storage->id,
            'root' => $root,
            'folders' => [],
            'databases' => [],
        ];

        try {
            $manifest['folders'] = $this->backupFolders($site, $server, $storage, $config->folders ?? [], $home, $root, $uploaded);
            $manifest['databases'] = $this->backupDatabases($server, $storage, $config->databases ?? [], $home, $root, $uploaded);
            $this->uploadManifest($server, $storage, $deployment, $home, $root, $manifest);

            $deployment->has_backups = true;
            $deployment->backup_manifest = $manifest;
            $deployment->save();
        } catch (Throwable $e) {
            $this->cleanupUploaded($server, $storage, $root, $uploaded);

            throw $e;
        }

        $this->prune($site, $config->keep);
    }

    /**
     * @param  array<int, string>  $folders
     * @param  array<int, string>  $uploaded
     * @return array<int, array<string, mixed>>
     */
    private function backupFolders(Site $site, Server $server, StorageProvider $storage, array $folders, string $home, string $root, array &$uploaded): array
    {
        if (empty($folders)) {
            $folders = [$site->path];
        }

        /** @var ?Deployment $activeRelease */
        $activeRelease = $site->deployments()->where('active', true)->whereNotNull('release')->first();
        $currentPrefix = $site->basePath().'/current';

        $entries = [];

        foreach ($folders as $index => $folder) {
            [$realPath, $relToRelease, $skip] = $this->resolveFolder($folder, $currentPrefix, $activeRelease);

            if ($skip) {
                continue;
            }

            $slug = Str::slug(basename($folder) ?: 'root').'-'.$index;
            $temp = $home.'/deploy-backup-'.$site->id.'-'.$slug.'.tar.gz';
            $file = 'folders/'.$slug.'.tar.gz';

            $server->os()->compress($realPath, $temp);
            $storage->provider()->ssh($server)->upload($temp, $storage->path($root.'/'.$file));
            $server->os()->deleteFile($temp);

            $uploaded[] = $file;
            $entries[] = [
                'abs_path' => $folder,
                'rel_to_release' => $relToRelease,
                'file' => $file,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, int>  $databaseIds
     * @param  array<int, string>  $uploaded
     * @return array<int, array<string, mixed>>
     */
    private function backupDatabases(Server $server, StorageProvider $storage, array $databaseIds, string $home, string $root, array &$uploaded): array
    {
        if (empty($databaseIds)) {
            return [];
        }

        $service = $server->database();

        if (! $service) {
            return [];
        }

        /** @var DatabaseHandler $handler */
        $handler = $service->handler();
        $entries = [];

        foreach ($databaseIds as $databaseId) {
            /** @var ?Database $database */
            $database = Database::query()->where('server_id', $server->id)->find($databaseId);

            if (! $database) {
                continue;
            }

            $slug = Str::slug($database->name).'-'.$database->id;
            $temp = $home.'/'.$slug.'.zip';
            $file = 'databases/'.$slug.'.zip';

            $handler->dumpDatabase($database->name, $slug);
            $storage->provider()->ssh($server)->upload($temp, $storage->path($root.'/'.$file));
            $server->os()->deleteFile($temp);

            $uploaded[] = $file;
            $entries[] = [
                'database_id' => $database->id,
                'name' => $database->name,
                'file' => $file,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function uploadManifest(Server $server, StorageProvider $storage, Deployment $deployment, string $home, string $root, array $manifest): void
    {
        $temp = $home.'/deploy-backup-'.$deployment->id.'-manifest.json';

        $server->os()->write($temp, (string) json_encode($manifest, JSON_PRETTY_PRINT));
        $storage->provider()->ssh($server)->upload($temp, $storage->path($root.'/manifest.json'));
        $server->os()->deleteFile($temp);
    }

    /**
     * Map a configured folder to a real path, dereferencing the modern `current` symlink.
     *
     * @return array{0: string, 1: ?string, 2: bool}
     */
    private function resolveFolder(string $folder, string $currentPrefix, ?Deployment $activeRelease): array
    {
        if ($folder === $currentPrefix || str_starts_with($folder, $currentPrefix.'/')) {
            if (! $activeRelease) {
                return ['', null, true];
            }

            $rel = ltrim(substr($folder, strlen($currentPrefix)), '/');
            $realPath = rtrim($activeRelease->path().'/'.$rel, '/');

            return [$realPath, $rel, false];
        }

        return [$folder, null, false];
    }

    /**
     * @param  array<int, string>  $uploaded
     */
    private function cleanupUploaded(Server $server, StorageProvider $storage, string $root, array $uploaded): void
    {
        $files = array_merge($uploaded, ['manifest.json']);

        foreach ($files as $file) {
            try {
                $storage->provider()->ssh($server)->delete($storage->path($root.'/'.$file));
            } catch (Throwable) {
            }
        }
    }

    private function prune(Site $site, int $keep): void
    {
        /** @var ?Deployment $lastToKeep */
        $lastToKeep = $site->deployments()
            ->where('has_backups', true)
            ->orderByDesc('id')
            ->skip($keep)
            ->first();

        if (! $lastToKeep) {
            return;
        }

        $toPrune = $site->deployments()
            ->where('has_backups', true)
            ->where('id', '<=', $lastToKeep->id)
            ->get();

        foreach ($toPrune as $deployment) {
            app(DeleteDeploymentBackup::class)->delete($deployment);
            $deployment->has_backups = false;
            $deployment->backup_manifest = null;
            $deployment->save();
        }
    }
}
