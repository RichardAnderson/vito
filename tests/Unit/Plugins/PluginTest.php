<?php

namespace Tests\Unit\Plugins;

use App\Actions\Plugins\BootPlugins;
use App\Actions\Plugins\DisablePlugin;
use App\Actions\Plugins\DiscoverPlugins;
use App\Actions\Plugins\EnablePlugin;
use App\Actions\Plugins\GetPluginInstance;
use App\Actions\Plugins\InstallPlugin;
use App\Actions\Plugins\UninstallPlugin;
use App\Models\Plugin;
use App\Models\PluginError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginTest extends TestCase
{
    use RefreshDatabase;

    private string $backupPath;

    private string $pluginPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginPath = app_path('Vito/Plugins');
        $this->backupPath = storage_path('plugins_backup_'.time());

        $this->movePlugins($this->pluginPath, $this->backupPath);
        File::makeDirectory($this->pluginPath, 0755, true);

        Plugin::truncate();
        PluginError::truncate();

        app(GetPluginInstance::class)->clear();
    }

    protected function tearDown(): void
    {
        $this->movePlugins($this->backupPath, $this->pluginPath);
        parent::tearDown();
    }

    private function installExamplePlugin(): Plugin
    {
        $fromFile = implode(DIRECTORY_SEPARATOR, [__DIR__, 'Example', 'Plugin.php']);
        $toFile = implode(DIRECTORY_SEPARATOR, [$this->pluginPath, 'Example', 'Plugin.php']);

        File::ensureDirectoryExists(dirname($toFile));
        if (! File::copy($fromFile, $toFile)) {
            $this->fail("Failed to copy example plugin from '$fromFile' to '$toFile'");
        }

        app(DiscoverPlugins::class)->handle();

        return Plugin::where('folder', 'Example')->first();
    }

    private function movePlugins(string $from, string $to): void
    {
        File::deleteDirectory($to);
        File::makeDirectory(path: $to, recursive: true, force: true);
        File::moveDirectory($from, $to, true);
    }

    private function getPluginPath(Plugin $plugin): string
    {
        return implode(DIRECTORY_SEPARATOR, [$this->pluginPath, $plugin->folder]);
    }

    private function createFakePlugin(): Plugin
    {
        $path = implode(DIRECTORY_SEPARATOR, [$this->pluginPath, 'ExampleRepo']);
        File::makeDirectory($path, 0755, true);

        app(DiscoverPlugins::class)->handle();

        return Plugin::where('folder', 'ExampleRepo')->first();
    }

    public function test_can_enable_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->save();

        $action = app(EnablePlugin::class);
        $action->handle($plugin);

        $plugin->refresh();
        $this->assertThat($plugin->is_enabled, $this->isTrue());
    }

    public function test_can_disable_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = true;
        $plugin->save();

        $disable = app(DisablePlugin::class);
        $disable->handle($plugin);

        $plugin->refresh();
        $this->assertThat($plugin->is_enabled, $this->isFalse());
    }

    public function test_can_discovery_plugins(): void
    {
        $plugin = $this->createFakePlugin();

        $this->assertNotNull($plugin);
        $this->assertThat($plugin->namespace, $this->equalTo('App\\Vito\\Plugins\\ExampleRepo\\Plugin'));
        $this->assertThat($plugin->is_installed, $this->isFalse());
        $this->assertThat($plugin->is_enabled, $this->isFalse());
    }

    public function test_install_invalid_plugin_raises_error(): void
    {
        $plugin = $this->createFakePlugin();

        $install = app(InstallPlugin::class);
        $this->assertThrows(fn () => $install->handle($plugin));

        $plugin->refresh();
        $errors = PluginError::where('plugin_id', $plugin->id)->get();

        $this->assertThat($plugin->is_installed, $this->isFalse());
        $this->assertCount(1, $errors);
    }

    public function test_can_remove_local_plugin(): void
    {
        $plugin = $this->createFakePlugin();
        $folder = $plugin->folder;
        $path = $this->getPluginPath($plugin);

        $uninstall = app(UninstallPlugin::class);
        $uninstall->handle($plugin);

        $plugin = Plugin::where('folder', $folder)->first();
        $this->assertThat($plugin, $this->isNull());
        $this->assertThat(File::isDirectory($path), $this->IsFalse());
    }

    public function test_can_uninstall_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = false;
        $plugin->save();

        $path = $this->getPluginPath($plugin);
        $folder = $plugin->folder;

        $uninstall = app(UninstallPlugin::class);
        $uninstall->handle($plugin);

        $plugin = Plugin::where('folder', $folder)->first();
        $this->assertThat($plugin, $this->isNull());
        $this->assertThat(File::isDirectory($path), $this->IsFalse());
    }

    public function test_cannot_uninstall_enabled_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = true;
        $plugin->save();

        $path = $this->getPluginPath($plugin);

        $uninstall = app(UninstallPlugin::class);
        $this->assertThrows(fn () => $uninstall->handle($plugin));

        $plugin->refresh();
        $this->assertThat($plugin->is_enabled, $this->isTrue());
        $this->assertThat($plugin->is_installed, $this->isTrue());
        $this->assertThat(File::isDirectory($path), $this->isTrue());
    }

    public function test_cannot_enable_enabled_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = true;
        $plugin->save();

        $enable = app(EnablePlugin::class);
        $this->assertThrows(fn () => $enable->handle($plugin));

        $plugin->refresh();
        $this->assertThat($plugin->is_enabled, $this->isTrue());
        $this->assertThat($plugin->is_installed, $this->isTrue());
    }

    public function test_cannot_disable_disabled_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = false;
        $plugin->save();

        $disable = app(DisablePlugin::class);
        $this->assertThrows(fn () => $disable->handle($plugin));

        $plugin->refresh();
        $this->assertThat($plugin->is_enabled, $this->isFalse());
        $this->assertThat($plugin->is_installed, $this->isTrue());
    }

    public function test_install_method_called(): void
    {
        $plugin = $this->installExamplePlugin();

        $installer = app(InstallPlugin::class);
        $installer->handle($plugin);

        $implementation = app(GetPluginInstance::class)->handle($plugin);
        $methods = $implementation->getMethods();

        $this->assertThat($methods, $this->arrayHasKey('install'));
        $this->assertCount(1, $methods);
        $this->assertThat($methods['install'], $this->equalTo(1));
    }

    public function test_enable_method_called(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->save();

        $action = app(EnablePlugin::class);
        $action->handle($plugin);

        $implementation = app(GetPluginInstance::class)->handle($plugin);
        $methods = $implementation->getMethods();

        $this->assertThat($methods, $this->arrayHasKey('enable'));
        $this->assertCount(1, $methods);
        $this->assertThat($methods['enable'], $this->equalTo(1));
    }

    public function test_disable_method_called(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = true;
        $plugin->save();

        $action = app(DisablePlugin::class);
        $action->handle($plugin);

        $implementation = app(GetPluginInstance::class)->handle($plugin);
        $methods = $implementation->getMethods();

        $this->assertThat($methods, $this->arrayHasKey('disable'));
        $this->assertCount(1, $methods);
        $this->assertThat($methods['disable'], $this->equalTo(1));
    }

    public function test_boot_method_called_for_enabled_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = true;
        $plugin->save();

        $action = app(BootPlugins::class);
        $action->handle();

        $implementation = app(GetPluginInstance::class)->handle($plugin);
        $methods = $implementation->getMethods();

        $this->assertThat($methods, $this->arrayHasKey('boot'));
        $this->assertCount(1, $methods);
        $this->assertThat($methods['boot'], $this->equalTo(1));
    }

    public function test_boot_method_not_called_for_disabled_plugin(): void
    {
        $plugin = $this->installExamplePlugin();
        $plugin->is_installed = true;
        $plugin->is_enabled = false;
        $plugin->save();

        $action = app(BootPlugins::class);
        $action->handle();

        $implementation = app(GetPluginInstance::class)->handle($plugin);
        $methods = $implementation->getMethods();

        $this->assertCount(0, $methods);
    }
}
