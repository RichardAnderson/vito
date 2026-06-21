<?php

namespace App\Sdk\Descriptors;

final readonly class PropertyDescriptor
{
    public function __construct(
        public string $name,
        public string $phpDocType,
        public string $tsType,
    ) {}
}
