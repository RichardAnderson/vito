<?php

namespace App\Sdk;

use App\Sdk\Descriptors\ProjectionDescriptor;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Vito\Plugin\Attributes\ExposesMethods;
use Vito\Plugin\Attributes\HostContract;

final class ProjectionRegistry
{
    /**
     * All Host API projections, deterministically ordered by contract name.
     *
     * @return list<ProjectionDescriptor>
     */
    public function all(): array
    {
        $reflections = $this->discover();

        $dataClassToContract = [];
        foreach ($reflections as $reflection) {
            $dataClassToContract[$reflection->getName()] = $this->contractName($reflection);
        }

        $mapper = new TypeMapper($dataClassToContract);

        $descriptors = [];
        foreach ($reflections as $reflection) {
            $descriptors[] = $this->describe($reflection, $mapper);
        }

        usort($descriptors, fn (ProjectionDescriptor $a, ProjectionDescriptor $b): int => strcmp($a->contractName, $b->contractName));

        return $descriptors;
    }

    /**
     * @return list<ReflectionClass<Data>>
     */
    private function discover(): array
    {
        $directory = app_path('Data');

        if (! is_dir($directory)) {
            return [];
        }

        $reflections = [];
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $class = 'App\\Data\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Data::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->getAttributes(HostContract::class) === []) {
                continue;
            }

            $reflections[] = $reflection;
        }

        return $reflections;
    }

    /**
     * @param  ReflectionClass<Data>  $reflection
     */
    private function describe(ReflectionClass $reflection, TypeMapper $mapper): ProjectionDescriptor
    {
        $constructor = $reflection->getConstructor();
        $properties = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $properties[] = $mapper->map($parameter);
        }

        $methods = [];
        $methodAttributes = $reflection->getAttributes(ExposesMethods::class);
        if ($methodAttributes !== []) {
            $methods = $methodAttributes[0]->newInstance()->methods;
        }

        /** @var class-string $model */
        $model = $this->hostContract($reflection)->model;

        return new ProjectionDescriptor(
            contractName: $this->contractName($reflection),
            modelClass: $model,
            dataClass: $reflection->getName(),
            properties: $properties,
            methods: $methods,
        );
    }

    /**
     * @param  ReflectionClass<Data>  $reflection
     */
    private function contractName(ReflectionClass $reflection): string
    {
        return class_basename($this->hostContract($reflection)->model);
    }

    /**
     * @param  ReflectionClass<Data>  $reflection
     */
    private function hostContract(ReflectionClass $reflection): HostContract
    {
        return $reflection->getAttributes(HostContract::class)[0]->newInstance();
    }
}
