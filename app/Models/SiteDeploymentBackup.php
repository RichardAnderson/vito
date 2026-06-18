<?php

namespace App\Models;

use Database\Factories\SiteDeploymentBackupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $site_id
 * @property bool $enabled
 * @property array<int, string> $folders
 * @property array<int, int> $databases
 * @property ?int $storage_id
 * @property int $keep
 * @property Site $site
 * @property ?StorageProvider $storage
 */
class SiteDeploymentBackup extends AbstractModel
{
    /** @use HasFactory<SiteDeploymentBackupFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'enabled',
        'folders',
        'databases',
        'storage_id',
        'keep',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'enabled' => 'boolean',
        'folders' => 'array',
        'databases' => 'array',
        'storage_id' => 'integer',
        'keep' => 'integer',
    ];

    /**
     * @return BelongsTo<Site, covariant $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<StorageProvider, covariant $this>
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(StorageProvider::class, 'storage_id');
    }
}
