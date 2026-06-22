<?php

namespace App\Observers;

use App\Actions\Backup\ManageBackupFile;
use App\Models\BackupFile;

final class BackupFileObserver
{
    public function created(BackupFile $backupFile): void
    {
        $keep = $backupFile->backup->keep_backups;
        if ($backupFile->backup->files()->count() <= $keep) {
            return;
        }

        /** @var ?BackupFile $lastFileToKeep */
        $lastFileToKeep = $backupFile->backup->files()->orderByDesc('id')->skip($keep)->first();
        if (! $lastFileToKeep) {
            return;
        }

        $files = $backupFile->backup->files()->where('id', '<=', $lastFileToKeep->id)->get();
        /** @var BackupFile $file */
        foreach ($files as $file) {
            app(ManageBackupFile::class)->delete($file);
        }
    }
}
