<?php

namespace App\Pages\Components\Forms;

use App\DTOs\DynamicField;
use App\Pages\Schema\EvaluatesClosures;
use App\Pages\Schema\EvaluationContext;
use Closure;

/**
 * Base for Filament-style form fields (TextInput, Select, …). Every setter is
 * closure-capable and evaluated per request; resolve() produces the wire-frozen
 * App\DTOs\DynamicField, so the frontend field renderer is unchanged.
 */
abstract class Field
{
    use EvaluatesClosures;

    protected string|Closure|null $label = null;

    protected mixed $default = null;

    protected string|Closure|null $placeholder = null;

    protected string|Closure|null $description = null;

    /**
     * @var array<int, mixed>|null
     */
    protected ?array $rules = null;

    public function __construct(protected readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function placeholder(string|Closure $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(string|Closure $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    abstract protected function applyType(DynamicField $field, EvaluationContext $ctx): void;

    public function resolve(EvaluationContext $ctx): DynamicField
    {
        $field = DynamicField::make($this->name);
        $this->applyType($field, $ctx);

        $label = $this->evaluate($this->label, $ctx);
        if ($label !== null) {
            $field->label((string) $label);
        }

        $field->default($this->evaluate($this->default, $ctx));

        $placeholder = $this->evaluate($this->placeholder, $ctx);
        if ($placeholder !== null) {
            $field->placeholder((string) $placeholder);
        }

        $description = $this->evaluate($this->description, $ctx);
        if ($description !== null) {
            $field->description((string) $description);
        }

        if ($this->rules !== null) {
            $field->rules($this->rules);
        }

        return $field;
    }
}
