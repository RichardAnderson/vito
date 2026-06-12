<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;

final class Repeater extends Field
{
    /**
     * @var array<int, Field>
     */
    private array $fields = [];

    /**
     * @param  array<int, Field>  $fields
     */
    public function schema(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->repeater(array_map(fn (Field $sub): DynamicField => $sub->resolve($ctx), $this->fields));
    }
}
