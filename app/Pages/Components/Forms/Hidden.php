<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;

/**
 * A non-rendered field that carries a value in the submitted payload — typically a
 * row id seeded from table-row context so a row-action edit dialog can bind the model.
 */
final class Hidden extends Field
{
    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->hidden();
    }
}
