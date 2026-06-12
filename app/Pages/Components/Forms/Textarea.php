<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;

final class Textarea extends Field
{
    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->textarea();
    }
}
