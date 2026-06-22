<?php

namespace Vito\Plugin\DTOs;

class DynamicField
{
    public function __construct(
        protected string $name,
        protected string $type = 'text',
        protected string $label = '',
        protected mixed $default = null,
        protected ?string $placeholder = null,
        protected ?string $description = null,
        protected ?array $options = null,
        protected ?array $optionLabels = null,
        protected ?array $link = null,
        protected ?string $className = null,
        protected ?array $componentProps = null,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function component(): static
    {
        $this->type = 'component';

        return $this;
    }

    public function text(): static
    {
        $this->type = 'text';

        return $this;
    }

    public function password(): static
    {
        $this->type = 'password';

        return $this;
    }

    public function passwordWithToggle(): static
    {
        $this->type = 'password-with-toggle';

        return $this;
    }

    public function textarea(): static
    {
        $this->type = 'textarea';

        return $this;
    }

    public function select(): static
    {
        $this->type = 'select';

        return $this;
    }

    public function checkbox(): static
    {
        $this->type = 'checkbox';

        return $this;
    }

    public function alert(): static
    {
        $this->type = 'alert';

        return $this;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function options(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  array<string, string>|null  $optionLabels
     */
    public function optionLabels(?array $optionLabels): static
    {
        $this->optionLabels = $optionLabels;

        return $this;
    }

    public function link(string $label, string $url): static
    {
        $this->link = [
            'label' => $label,
            'url' => $url,
        ];

        return $this;
    }

    public function className(?string $className): static
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function componentProps(array $props): static
    {
        $this->componentProps = $props;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
            'componentProps' => $this->componentProps,
        ];
    }
}
