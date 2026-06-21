<?php

namespace App\Console\Commands\Sdk;

use App\Sdk\Generators\PhpContractGenerator;
use App\Sdk\Generators\TypeScriptGenerator;
use App\Sdk\ProjectionRegistry;
use App\Sdk\SdkPaths;
use Illuminate\Console\Command;

class CheckSdkCommand extends Command
{
    protected $signature = 'sdk:check';

    protected $description = 'Fail if the committed Plugin SDK contracts / wire types are out of date with the app/Data projections.';

    public function handle(ProjectionRegistry $registry, PhpContractGenerator $php, TypeScriptGenerator $ts): int
    {
        $descriptors = $registry->all();
        $drifted = [];

        foreach ($php->generate($descriptors) as $contract => $expected) {
            if ($this->committed(SdkPaths::contractFile($contract)) !== $expected) {
                $drifted[] = 'Contracts/'.$contract.'.php';
            }
        }

        if ($this->committed(SdkPaths::typeScriptFile()) !== $ts->generate($descriptors)) {
            $drifted[] = 'generated.ts';
        }

        if ($drifted !== []) {
            $this->error('Plugin SDK artifacts are out of date: '.implode(', ', $drifted));
            $this->line('Run `php artisan sdk:generate` and commit the result.');

            return self::FAILURE;
        }

        $this->info('Plugin SDK artifacts are up to date.');

        return self::SUCCESS;
    }

    private function committed(string $path): ?string
    {
        return is_file($path) ? (string) file_get_contents($path) : null;
    }
}
