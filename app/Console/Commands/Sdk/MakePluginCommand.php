<?php

namespace App\Console\Commands\Sdk;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    protected $signature = 'plugin:new {name : The plugin name, e.g. Backups}';

    protected $description = 'Scaffold a new Vito plugin wired against the Plugin SDK.';

    public function handle(): int
    {
        $input = (string) $this->argument('name');
        $plugin = Str::studly($input);
        $prefix = Str::kebab($plugin);
        $dir = app_path("Vito/Plugins/{$plugin}");

        if (is_dir($dir)) {
            $this->error("app/Vito/Plugins/{$plugin} already exists.");

            return self::FAILURE;
        }

        @mkdir($dir.'/Actions', 0755, true);
        @mkdir($dir.'/resources/js', 0755, true);

        $replace = [
            '{{plugin}}' => $plugin,
            '{{prefix}}' => $prefix,
        ];

        foreach ($this->files() as $relative => $stub) {
            file_put_contents($dir.'/'.$relative, strtr($stub, $replace));
        }

        $this->info("Scaffolded {$plugin} at app/Vito/Plugins/{$plugin}.");
        $this->line('Install it from Admin → Plugins (or run the discover lifecycle).');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function files(): array
    {
        return [
            'Plugin.php' => <<<'PHP'
<?php

namespace App\Vito\Plugins\{{plugin}};

use Vito\Plugin\PluginInterface;

final class Plugin implements PluginInterface
{
    public function boot(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function install(): void {}

    public function uninstall(): void {}

    public function getName(): string
    {
        return '{{plugin}}';
    }

    public function getDescription(): string
    {
        return 'A Vito plugin built with the Plugin SDK.';
    }
}

PHP,
            'Actions/ExampleAction.php' => <<<'PHP'
<?php

namespace App\Vito\Plugins\{{plugin}}\Actions;

use Vito\Plugin\Contracts\Server;
use Vito\Plugin\Contracts\Site;
use Vito\Plugin\Facades\Broadcast;
use Vito\Plugin\Facades\Ssh;

final class ExampleAction
{
    public function handle(Server $server, Site $site): string
    {
        $output = trim(Ssh::init($server)->exec('echo hello from {{prefix}}'));

        Broadcast::dispatch($server->project_id, '{{prefix}}.ran', [
            'site' => $site->domain,
            'output' => $output,
        ]);

        return $output;
    }
}

PHP,
            'resources/js/index.ts' => <<<'TS'
import type { Server, Site } from '@vito/plugin-sdk';

export function summarize(server: Server, site: Site): string {
  return `${site.domain} on ${server.name}`;
}

TS,
            'vito-plugin.json' => <<<'JSON'
{
  "name": "{{prefix}}",
  "requires": { "vito": "^5.0", "sdk": "^1" },
  "capabilities": ["ssh"],
  "assets": { "entry": "dist/index.js" }
}

JSON,
        ];
    }
}
