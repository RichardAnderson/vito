<?php

namespace App\DTOs;

readonly class DynamicForm
{
    /**
     * @param  array<int, DynamicField>  $fields
     */
    public function __construct(
        private array $fields = [],
    ) {}

    /**
     * @param  array<int, DynamicField>  $fields
     */
    public static function make(array $fields): self
    {
        return new self($fields);
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $field) {
            $fields[] = $field->toArray();
        }

        return $fields;
    }

    public function getFieldNames(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $name = $field->toArray()['name'] ?? null;
            if ($name) {
                $fields[] = $name;
            }
        }

        return $fields;
    }

    /**
     * Build the Laravel validation ruleset from the fields' `rules()`. Repeater
     * subfields validate as `{name}.*.{subfield}` so nested error paths line up
     * with the frontend's `{name}.{index}.{subfield}` form keys.
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $fieldRules = $field->getRules();
            if ($fieldRules !== null) {
                $rules[$field->getName()] = $fieldRules;
            }

            foreach ($field->getFields() ?? [] as $subField) {
                $subRules = $subField->getRules();
                if ($subRules !== null) {
                    $rules[$field->getName().'.*.'.$subField->getName()] = $subRules;
                }
            }
        }

        return $rules;
    }
}
