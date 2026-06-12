<?php

namespace Tests\Unit\Pages;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use Tests\TestCase;

class DynamicFormRulesTest extends TestCase
{
    public function test_collects_field_level_rules(): void
    {
        $form = DynamicForm::make([
            DynamicField::make('version')->select()->rules(['required', 'string']),
            DynamicField::make('note')->text(),
        ]);

        $this->assertSame(['version' => ['required', 'string']], $form->validationRules());
    }

    public function test_builds_nested_repeater_rules(): void
    {
        $form = DynamicForm::make([
            DynamicField::make('users')->repeater([
                DynamicField::make('username')->text()->rules(['required', 'string']),
                DynamicField::make('password')->password()->rules(['required', 'min:8']),
            ]),
        ]);

        $this->assertSame([
            'users.*.username' => ['required', 'string'],
            'users.*.password' => ['required', 'min:8'],
        ], $form->validationRules());
    }

    public function test_repeater_serializes_subfields(): void
    {
        $field = DynamicField::make('users')->repeater([
            DynamicField::make('username')->text(),
        ])->toArray();

        $this->assertSame('repeater', $field['type']);
        $this->assertCount(1, $field['fields']);
        $this->assertSame('username', $field['fields'][0]['name']);
    }

    public function test_plain_field_serialization_unchanged_without_rules(): void
    {
        $field = DynamicField::make('note')->text()->toArray();

        $this->assertArrayNotHasKey('rules', $field);
        $this->assertArrayNotHasKey('fields', $field);
    }
}
