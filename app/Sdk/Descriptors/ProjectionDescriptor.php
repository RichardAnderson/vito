<?php

namespace App\Sdk\Descriptors;

final readonly class ProjectionDescriptor
{
    /**
     * @param  class-string  $modelClass
     * @param  class-string  $dataClass
     * @param  list<PropertyDescriptor>  $properties
     * @param  array<string, string>  $methods
     */
    public function __construct(
        public string $contractName,
        public string $modelClass,
        public string $dataClass,
        public array $properties,
        public array $methods,
    ) {}
}
