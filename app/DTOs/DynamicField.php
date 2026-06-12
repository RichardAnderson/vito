<?php

namespace App\DTOs;

use App\Tooling\ToolingInterface;

class DynamicField
{
    private ?string $component = null;

    public function __construct(
        private string $name,
        private string $type = 'text',
        private string $label = '',
        private mixed $default = null,
        private ?string $placeholder = null,
        private ?string $description = null,
        private ?array $options = null,
        private ?array $optionLabels = null,
        private ?array $link = null,
        private ?string $className = null,
        private ?array $componentProps = null,
        private ?array $rules = null,
        private ?array $fields = null,
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Render this field via a registered custom React control. The optional name
     * keys the frontend control registry; when null the field's name is used.
     */
    public function component(?string $name = null): self
    {
        $this->type = 'component';
        $this->component = $name;

        return $this;
    }

    public function text(): self
    {
        $this->type = 'text';

        return $this;
    }

    /**
     * A non-rendered field whose value is carried in the form payload (e.g. a row id
     * seeded from table-row context for an edit dialog).
     */
    public function hidden(): self
    {
        $this->type = 'hidden';

        return $this;
    }

    public function password(): self
    {
        $this->type = 'password';

        return $this;
    }

    public function passwordWithToggle(): self
    {
        $this->type = 'password-with-toggle';

        return $this;
    }

    public function textarea(): self
    {
        $this->type = 'textarea';

        return $this;
    }

    public function select(): self
    {
        $this->type = 'select';

        return $this;
    }

    public function checkbox(): self
    {
        $this->type = 'checkbox';

        return $this;
    }

    /**
     * An array-of-subfields rows editor (e.g. Basic Auth users). Subfields carry
     * their own rules, validated as `{name}.*.{subfield}` with nested error paths.
     *
     * @param  array<int, DynamicField>  $fields
     */
    public function repeater(array $fields): self
    {
        $this->type = 'repeater';
        $this->fields = $fields;

        return $this;
    }

    public function alert(): self
    {
        $this->type = 'alert';

        return $this;
    }

    public function tooling(): self
    {
        $this->type = 'tooling';

        return $this;
    }

    /**
     * @param  class-string<ToolingInterface>  $toolClass
     */
    public function toolingPicker(string $toolClass): self
    {
        $this->type = 'tooling-picker';
        $this->options = [$toolClass::id()];

        if ($this->label === '') {
            $this->label = $toolClass::label().' Version';
        }

        $versions = $toolClass::supportedVersions();
        if ($versions !== []) {
            $this->default = $versions[0];
        }

        return $this;
    }

    /**
     * @param  array<int, class-string<ToolingInterface>>  $toolClasses
     * @param  array<class-string<ToolingInterface>, string>  $labelOverrides
     * @param  class-string<ToolingInterface>|null  $default
     */
    public function toolingSelector(array $toolClasses, array $labelOverrides = [], ?string $default = null, bool $allowNone = false): self
    {
        $this->type = 'tooling-selector';
        $this->options = array_map(fn (string $cls) => $cls::id(), $toolClasses);

        $labels = [];
        foreach ($labelOverrides as $cls => $label) {
            $labels[$cls::id()] = $label;
        }

        if ($allowNone) {
            array_unshift($this->options, 'none');
            $labels['none'] = 'None';
        }

        $this->optionLabels = $labels === [] ? null : $labels;

        if ($default !== null) {
            $this->default = $default::id();
        } elseif ($allowNone) {
            $this->default = 'none';
        } elseif ($this->default === null && $this->options !== []) {
            $this->default = $this->options[0];
        }

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function placeholder(?string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function options(?array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->link = [
            'label' => $label,
            'url' => $url,
        ];

        return $this;
    }

    public function className(?string $className): self
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function componentProps(array $props): self
    {
        $this->componentProps = $props;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getRules(): ?array
    {
        return $this->rules;
    }

    /**
     * @return array<int, DynamicField>|null
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'default' => $this->default,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'options' => $this->options,
            'optionLabels' => $this->optionLabels,
            'link' => $this->link,
            'className' => $this->className,
            'component' => $this->component,
            'componentProps' => $this->componentProps,
        ];

        if ($this->rules !== null) {
            $data['rules'] = $this->rules;
        }

        if ($this->fields !== null) {
            $data['fields'] = array_map(fn (self $field): array => $field->toArray(), $this->fields);
        }

        return $data;
    }
}
