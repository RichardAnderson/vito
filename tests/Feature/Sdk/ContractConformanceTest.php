<?php

namespace Tests\Feature\Sdk;

use App\Sdk\ProjectionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_model_satisfies_its_generated_host_contract(): void
    {
        $descriptors = app(ProjectionRegistry::class)->all();
        $this->assertNotEmpty($descriptors, 'No Host API projections were discovered.');

        foreach ($descriptors as $descriptor) {
            $model = $descriptor->modelClass;
            $contract = 'Vito\\Plugin\\Contracts\\'.$descriptor->contractName;

            $this->assertArrayHasKey(
                $contract,
                class_implements($model),
                "{$model} must implement {$contract}.",
            );

            /** @var Model $instance */
            $instance = new $model;
            $columns = Schema::getColumnListing($instance->getTable());

            foreach ($descriptor->properties as $property) {
                $name = $property->name;

                $addressable = in_array($name, $columns, true)
                    || method_exists($model, $name)
                    || method_exists($model, 'get'.Str::studly($name).'Attribute');

                $this->assertTrue(
                    $addressable,
                    "{$model}::\${$name} is exposed by {$contract} but is not addressable (no column, relation, or accessor).",
                );
            }
        }
    }
}
