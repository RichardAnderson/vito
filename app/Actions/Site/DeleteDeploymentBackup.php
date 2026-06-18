<?php

namespace App\Actions\Site;

use App\Facades\Notifier;
use App\Models\Deployment;
use App\Models\StorageProvider;
use App\Notifications\FailedToDeleteDeploymentBackup;
use Throwable;

class DeleteDeploymentBackup
{
    /**
     * Best-effort removal of a deployment's backup files from its storage provider.
     */
    public function delete(Deployment $deployment): void
    {
        $manifest = $deployment->backup_manifest;

        if (! $deployment->has_backups || ! $manifest) {
            return;
        }

        $root = $manifest['root'] ?? null;
        $storage = StorageProvider::query()->find($manifest['storage_id'] ?? null);

        if (! $root || ! $storage) {
            return;
        }

        $server = $deployment->site->server;
        $provider = $storage->provider()->ssh($server);
        $failed = false;

        foreach ($this->files($manifest) as $file) {
            try {
                $provider->delete($storage->path($root.'/'.$file));
            } catch (Throwable) {
                $failed = true;
            }
        }

        if ($failed) {
            Notifier::send($server, new FailedToDeleteDeploymentBackup($deployment));
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    private function files(array $manifest): array
    {
        $files = ['manifest.json'];

        foreach ($manifest['folders'] ?? [] as $folder) {
            if (isset($folder['file'])) {
                $files[] = $folder['file'];
            }
        }

        foreach ($manifest['databases'] ?? [] as $database) {
            if (isset($database['file'])) {
                $files[] = $database['file'];
            }
        }

        return $files;
    }
}
