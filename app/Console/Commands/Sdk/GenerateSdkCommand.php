<?php

namespace App\Console\Commands\Sdk;

use App\Sdk\Generators\PhpContractGenerator;
use App\Sdk\Generators\TypeScriptGenerator;
use App\Sdk\ProjectionRegistry;
use App\Sdk\SdkPaths;
use Illuminate\Console\Command;

class GenerateSdkCommand extends Command
{
    protected $signature = 'sdk:generate';

    protected $description = 'Generate the Plugin SDK Host API contracts and TypeScript wire types from the app/Data projections.';

    public function handle(ProjectionRegistry $registry, PhpContractGenerator $php, TypeScriptGenerator $ts): int
    {
        $descriptors = $registry->all();

        foreach ($php->generate($descriptors) as $contract => $contents) {
            file_put_contents(SdkPaths::contractFile($contract), $contents);
        }

        file_put_contents(SdkPaths::typeScriptFile(), $ts->generate($descriptors));

        $this->info(sprintf('Generated %d Host API contract(s) and the TypeScript wire types.', count($descriptors)));

        return self::SUCCESS;
    }
}
