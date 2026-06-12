<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;

final class Checkbox extends Field
{
    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->checkbox();
    }
}
