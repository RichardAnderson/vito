<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * A form field rendered by a registered custom React control (the field-control
 * registry). Resolves to a `component`-type DynamicField carrying the control name
 * and serializable props.
 */
final class Control extends Field
{
    private ?string $using = null;

    /**
     * @var array<string, mixed>|Closure|null
     */
    private array|Closure|null $componentProps = null;

    public function using(string $name): self
    {
        $this->using = $name;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $props
     */
    public function componentProps(array|Closure $props): self
    {
        $this->componentProps = $props;

        return $this;
    }

    protected function applyType(DynamicField $field, EvaluationContext $ctx): void
    {
        $field->component($this->using);

        $props = $this->evaluate($this->componentProps, $ctx);
        if (is_array($props)) {
            $field->componentProps($props);
        }
    }
}
