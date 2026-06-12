<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;

final class Password extends Field
{
    private bool $toggle = false;

    public function toggle(bool $toggle = true): self
    {
        $this->toggle = $toggle;

        return $this;
    }

    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $this->toggle ? $field->passwordWithToggle() : $field->password();
    }
}
