<?php

namespace Tests\Feature;

use App\Actions\Site\RestoreDeploymentBackup;
use App\Actions\Site\RunDeploymentBackup;
use App\Enums\DeploymentStatus;
use App\Facades\SSH;
use App\Models\Database;
use App\Models\Deployment;
use App\Models\SiteDeploymentBackup;
use App\Models\StorageProvider;
use App\Services\Database\Database as DatabaseHandler;
use App\StorageProviders\Local;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentBackupTest extends TestCase
{
    use RefreshDatabase;

    protected StorageProvider $storageProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageProvider = StorageProvider::factory()->create([
            'user_id' => $this->user->id,
            'provider' => Local::id(),
            'credentials' => ['path' => '/backups'],
        ]);
    }

    public function test_update_deployment_backup_persists_settings(): void
    {
        $this->actingAs($this->user);

        $database = Database::factory()->create(['server_id' => $this->server->id]);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => [$this->site->path],
            'databases' => [$database->id],
            'storage_id' => $this->storageProvider->id,
            'keep' => 3,
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('site_deployment_backups', [
            'site_id' => $this->site->id,
            'enabled' => true,
            'storage_id' => $this->storageProvider->id,
            'keep' => 3,
        ]);
    }

    public function test_folder_outside_site_directory_is_rejected(): void
    {
        $this->actingAs($this->user);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => ['/etc/passwd'],
            'storage_id' => $this->storageProvider->id,
            'keep' => 3,
        ])->assertSessionHasErrors('folders.0');
    }

    public function test_folder_with_shell_metacharacters_is_rejected(): void
    {
        $this->actingAs($this->user);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => [$this->site->path."/'; rm -rf /"],
            'storage_id' => $this->storageProvider->id,
            'keep' => 3,
        ])->assertSessionHasErrors('folders.0');
    }

    public function test_nonexistent_database_is_rejected(): void
    {
        $this->actingAs($this->user);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => [$this->site->path],
            'databases' => [9999],
            'storage_id' => $this->storageProvider->id,
            'keep' => 3,
        ])->assertSessionHasErrors('databases.0');
    }

    public function test_storage_is_required_when_enabled(): void
    {
        $this->actingAs($this->user);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => [$this->site->path],
            'keep' => 3,
        ])->assertSessionHasErrors('storage_id');
    }

    public function test_keep_cannot_exceed_history(): void
    {
        $this->actingAs($this->user);

        $this->put(route('application.update-deployment-backup', ['server' => $this->server, 'site' => $this->site]), [
            'enabled' => true,
            'folders' => [$this->site->path],
            'storage_id' => $this->storageProvider->id,
            'keep' => 9999,
        ])->assertSessionHasErrors('keep');
    }

    public function test_run_deployment_backup_captures_folders_and_databases(): void
    {
        SSH::fake();

        $database = Database::factory()->create(['server_id' => $this->server->id, 'name' => 'app_db']);

        SiteDeploymentBackup::factory()->create([
            'site_id' => $this->site->id,
            'enabled' => true,
            'folders' => [$this->site->path],
            'databases' => [$database->id],
            'storage_id' => $this->storageProvider->id,
            'keep' => 5,
        ]);

        $deployment = $this->deployment();

        app(RunDeploymentBackup::class)->run($deployment);

        $deployment->refresh();
        $this->assertTrue($deployment->has_backups);
        $this->assertCount(1, $deployment->backup_manifest['folders']);
        $this->assertCount(1, $deployment->backup_manifest['databases']);
        $this->assertEquals($database->id, $deployment->backup_manifest['databases'][0]['database_id']);
        $this->assertEquals('deploy/'.$this->site->id.'/'.$deployment->id, $deployment->backup_manifest['root']);

        SSH::assertExecutedContains("mysqldump -u root 'app_db'");

        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'has_backups' => true,
        ]);
    }

    public function test_run_deployment_backup_aborts_without_storage(): void
    {
        SSH::fake();

        SiteDeploymentBackup::factory()->create([
            'site_id' => $this->site->id,
            'enabled' => true,
            'folders' => [$this->site->path],
            'databases' => [],
            'storage_id' => null,
            'keep' => 5,
        ]);

        $deployment = $this->deployment();

        $this->expectException(\Exception::class);

        app(RunDeploymentBackup::class)->run($deployment);
    }

    public function test_retention_prunes_old_deployment_backups(): void
    {
        SSH::fake();

        SiteDeploymentBackup::factory()->create([
            'site_id' => $this->site->id,
            'enabled' => true,
            'folders' => [$this->site->path],
            'databases' => [],
            'storage_id' => $this->storageProvider->id,
            'keep' => 1,
        ]);

        $old = $this->deployment();
        $old->forceFill(['has_backups' => true, 'backup_manifest' => $this->manifest($old)])->save();

        $new = $this->deployment();
        app(RunDeploymentBackup::class)->run($new);

        $this->assertFalse($old->refresh()->has_backups);
        $this->assertNull($old->backup_manifest);
        $this->assertTrue($new->refresh()->has_backups);
    }

    public function test_deleting_deployment_removes_backups(): void
    {
        SSH::fake();

        $deployment = $this->deployment();
        $deployment->forceFill(['has_backups' => true, 'backup_manifest' => $this->manifest($deployment)])->save();

        $deployment->delete();

        SSH::assertExecutedContains('manifest.json');
        $this->assertDatabaseMissing('deployments', ['id' => $deployment->id]);
    }

    public function test_restore_deployment_backup_runs_over_ssh(): void
    {
        SSH::fake();

        $database = Database::factory()->create(['server_id' => $this->server->id, 'name' => 'app_db']);

        $deployment = $this->deployment();
        $deployment->forceFill([
            'has_backups' => true,
            'backup_manifest' => [
                'storage_id' => $this->storageProvider->id,
                'root' => 'deploy/'.$this->site->id.'/'.$deployment->id,
                'folders' => [
                    ['abs_path' => $this->site->path, 'rel_to_release' => null, 'file' => 'folders/root-0.tar.gz'],
                ],
                'databases' => [
                    ['database_id' => $database->id, 'name' => 'app_db', 'file' => 'databases/app_db-'.$database->id.'.zip'],
                ],
            ],
        ]);

        $this->actingAs($this->user);

        $this->post(route('application.deployments.restore-backup', [
            'server' => $this->server,
            'site' => $this->site,
            'deployment' => $deployment,
        ]))->assertSessionDoesntHaveErrors();

        SSH::assertExecutedContains('Starting atomic archive restore');
        SSH::assertExecutedContains("mysql -u root 'app_db'");
    }

    public function test_restore_rejected_when_no_backups(): void
    {
        $this->actingAs($this->user);

        $deployment = $this->deployment();

        $this->post(route('application.deployments.restore-backup', [
            'server' => $this->server,
            'site' => $this->site,
            'deployment' => $deployment,
        ]))->assertSessionHasErrors('backup');
    }

    public function test_database_restore_uses_bare_name(): void
    {
        SSH::fake();


        /** @var DatabaseHandler $handler */
        $handler = $this->server->database()->handler();
        $handler->importDatabase('testdb', 'backupzip');

        SSH::assertExecutedContains('unzip backupzip.zip');
        SSH::assertExecutedContains('< backupzip.sql');
    }

    private function deployment(): Deployment
    {
        return Deployment::factory()->create([
            'site_id' => $this->site->id,
            'deployment_script_id' => $this->site->deploymentScript->id,
            'log_id' => null,
            'status' => DeploymentStatus::FINISHED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(Deployment $deployment): array
    {
        return [
            'storage_id' => $this->storageProvider->id,
            'root' => 'deploy/'.$this->site->id.'/'.$deployment->id,
            'folders' => [
                ['abs_path' => $this->site->path, 'rel_to_release' => null, 'file' => 'folders/root-0.tar.gz'],
            ],
            'databases' => [],
        ];
    }
}
