<?php

namespace App\Plugins;

class RegisterComponentFunction
{
    /**
     * @param  array<int, string>  $unlocks
     */
    public function __construct(
        public string $name,
        public string $label = '',
        public string $description = '',
        public string $color = 'gray',
        public array $unlocks = [],
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @param  array<int, string>  $unlocks
     */
    public function unlocks(array $unlocks): self
    {
        $this->unlocks = $unlocks;

        return $this;
    }

    public function register(): void
    {
        $functions = config('component.functions');

        $functions[$this->name] = [
            'label' => $this->label,
            'description' => $this->description,
            'color' => $this->color,
            'unlocks' => $this->unlocks,
        ];

        config(['component.functions' => $functions]);
    }
}
