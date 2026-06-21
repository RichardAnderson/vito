<?php

namespace App\Console\Commands\Sdk;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MakeSdkProjectionCommand extends Command
{
    protected $signature = 'make:sdk-projection {model : The model class name, e.g. Worker or App\\Models\\Worker}';

    protected $description = 'Scaffold a review-stub app/Data projection for a model from its columns and casts.';

    public function handle(): int
    {
        $input = (string) $this->argument('model');
        $modelClass = str_contains($input, '\\') ? $input : 'App\\Models\\'.$input;

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->error("Model {$modelClass} not found.");

            return self::FAILURE;
        }

        $short = class_basename($modelClass);
        $target = app_path('Data/'.$short.'Data.php');

        if (is_file($target)) {
            $this->error("app/Data/{$short}Data.php already exists.");

            return self::FAILURE;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $casts = $model->getCasts();
        $skip = ['created_at', 'updated_at', 'deleted_at'];

        $props = [];
        $enumImports = [];
        foreach (Schema::getColumnListing($model->getTable()) as $column) {
            if (in_array($column, $skip, true)) {
                continue;
            }

            $cast = $casts[$column] ?? null;

            if (is_string($cast) && enum_exists($cast)) {
                $enumImports[$cast] = true;
                $props[] = '        public '.class_basename($cast).' $'.$column.',';

                continue;
            }

            if (str_starts_with((string) $cast, 'encrypted') || in_array($cast, ['array', 'json', 'object', 'collection'], true)) {
                continue; // complex casts need manual typing — left out of the stub
            }

            $type = match (true) {
                in_array($cast, ['int', 'integer'], true), $column === 'id', str_ends_with($column, '_id') => 'int',
                in_array($cast, ['bool', 'boolean'], true) => 'bool',
                in_array($cast, ['float', 'double', 'decimal'], true) => 'float',
                default => 'string',
            };
            $props[] = '        public '.$type.' $'.$column.',';
        }

        $imports = [
            'use App\\Models\\'.$short.';',
            'use Spatie\\LaravelData\\Data;',
            'use Vito\\Plugin\\Attributes\\ExposesMethods;',
            'use Vito\\Plugin\\Attributes\\HostContract;',
        ];
        foreach (array_keys($enumImports) as $enum) {
            $imports[] = 'use '.$enum.';';
        }
        sort($imports);

        $contents = implode("\n", [
            '<?php',
            '',
            'namespace App\\Data;',
            '',
            implode("\n", $imports),
            '',
            '// REVIEW STUB scaffolded by make:sdk-projection. Trim to the deliberate public surface,',
            '// add nested *Data relations, and curate #[ExposesMethods] before committing.',
            '#[HostContract('.$short.'::class)]',
            '#[ExposesMethods([])]',
            'final class '.$short.'Data extends Data',
            '{',
            '    public function __construct(',
            implode("\n", $props),
            '    ) {}',
            '}',
            '',
        ]);

        file_put_contents($target, $contents);
        $this->info("Scaffolded app/Data/{$short}Data.php — review before committing.");

        return self::SUCCESS;
    }
}
