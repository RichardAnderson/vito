<?php

namespace App\DTOs;

use App\Tooling\ToolingInterface;
use Vito\Plugin\DTOs\DynamicField as BaseDynamicField;

class DynamicField extends BaseDynamicField
{
    public function tooling(): static
    {
        $this->type = 'tooling';

        return $this;
    }

    /**
     * @param  class-string<ToolingInterface>  $toolClass
     */
    public function toolingPicker(string $toolClass): static
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
    public function toolingSelector(array $toolClasses, array $labelOverrides = [], ?string $default = null, bool $allowNone = false): static
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
}
