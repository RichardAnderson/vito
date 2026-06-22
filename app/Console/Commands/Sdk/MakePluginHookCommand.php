<?php

namespace App\Console\Commands\Sdk;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginHookCommand extends Command
{
    protected $signature = 'make:plugin-hook
        {name : The hook class name, e.g. ShouldDeploy}
        {--action : Generate a run-all ActionHook}
        {--decision : Generate a boolean DecisionHook}
        {--default=true : DecisionHook default (true = proceed/fail-open, false = block/fail-open)}
        {--fail-closed : DecisionHook forces the safe value when a listener errors}';

    protected $description = 'Scaffold a class-based plugin hook into the vito/plugin-sdk package.';

    public function handle(): int
    {
        $name = Str::studly(class_basename(str_replace('\\', '/', (string) $this->argument('name'))));
        if ($name === '' || $name !== $this->argument('name')) {
            $this->error('Provide a bare hook class name (no namespace) — hooks live in Vito\\Plugin\\Hooks.');

            return self::FAILURE;
        }

        $isAction = (bool) $this->option('action');
        $isDecision = (bool) $this->option('decision');
        if ($isAction === $isDecision) {
            $this->error('Choose exactly one of --action or --decision.');

            return self::FAILURE;
        }

        $default = filter_var($this->option('default'), FILTER_VALIDATE_BOOL);
        $failClosed = (bool) $this->option('fail-closed');
        if ($isDecision && $failClosed && $default === false) {
            $this->error('--fail-closed is a no-op for a default=false hook (the safe value is already the default).');

            return self::FAILURE;
        }

        $path = base_path('packages/plugin-sdk-php/src/Hooks/'.$name.'.php');
        if (file_exists($path)) {
            $this->error("Hook {$name} already exists at {$path}.");

            return self::FAILURE;
        }

        file_put_contents($path, $isAction ? $this->actionStub($name) : $this->decisionStub($name, $default, $failClosed));

        $this->info("Created {$name} in vito/plugin-sdk. Declare its inputs as SDK contracts in the constructor, then run sdk:snapshot.");

        return self::SUCCESS;
    }

    private function actionStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace Vito\\Plugin\\Hooks;

        final class {$name} extends ActionHook
        {
            // Declare inputs as SDK contracts only (Vito\\Plugin\\Contracts\\*), e.g.:
            // public function __construct(public readonly \\Vito\\Plugin\\Contracts\\Site \$site) {}
        }

        PHP;
    }

    private function decisionStub(string $name, bool $default, bool $failClosed): string
    {
        $defaultLiteral = $default ? 'true' : 'false';
        $safeValue = $failClosed ? "\n\n    protected ?bool \$safeValue = ".($default ? 'false' : 'true').';' : '';

        return <<<PHP
        <?php

        namespace Vito\\Plugin\\Hooks;

        final class {$name} extends DecisionHook
        {
            protected bool \$default = {$defaultLiteral};{$safeValue}

            // Declare inputs as SDK contracts only (Vito\\Plugin\\Contracts\\*), e.g.:
            // public function __construct(public readonly \\Vito\\Plugin\\Contracts\\Site \$site) {}
        }

        PHP;
    }
}
