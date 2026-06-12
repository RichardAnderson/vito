<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;
use Closure;

final class Select extends Field
{
    /**
     * @var array<array-key, mixed>|Closure|null
     */
    private array|Closure|null $options = null;

    /**
     * @param  array<array-key, mixed>|Closure  $options
     */
    public function options(array|Closure $options): self
    {
        $this->options = $options;

        return $this;
    }

    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->select();

        $options = $this->evaluate($this->options, $ctx);
        if ($options !== null) {
            $field->options($options);
        }
    }
}
