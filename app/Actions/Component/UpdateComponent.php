<?php

namespace App\Actions\Component;

use App\Support\ComponentFunctions;
use App\Enums\ComponentType;
use App\Models\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateComponent
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(Component $component, array $input): Component
    {
        $this->validate($component, $input);

        DB::transaction(function () use ($component, $input): void {
            $component->fill([
                'name' => $input['name'],
                'web_directory' => $input['web_directory'] ?? $component->web_directory,
                'php_version' => $input['php_version'] ?? $component->php_version,
            ])->save();

            if ($component->type === ComponentType::APP && isset($input['functions'])) {
                app(SyncComponentFunctions::class)->sync($component, $input['functions']);
            }
        });

        return $component->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Component $component, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('components', 'name')
                    ->where('environment_id', $component->environment_id)
                    ->ignore($component->id),
            ],
            'functions' => ['array'],
            'functions.*' => ['string', Rule::in(ComponentFunctions::ids())],
            'php_version' => ['nullable', 'string', 'max:8'],
            'web_directory' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $functions = $component->type === ComponentType::APP ? ($input['functions'] ?? []) : [];

        if ($component->type === ComponentType::APP && isset($input['functions']) && $functions === []) {
            throw ValidationException::withMessages([
                'functions' => 'Select at least one function for an app component.',
            ]);
        }

        $this->assertSingleScheduler($component, $functions);
    }

    /**
     * @param  array<int, string>  $functions
     *
     * @throws ValidationException
     */
    private function assertSingleScheduler(Component $component, array $functions): void
    {
        if (! in_array(ComponentFunctions::SCHEDULER, $functions, true)) {
            return;
        }

        $exists = $component->environment->components()
            ->whereKeyNot($component->id)
            ->whereHas('functions', fn ($query) => $query->where('function', ComponentFunctions::SCHEDULER))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'functions' => 'Another component in this environment already runs the scheduler.',
            ]);
        }
    }
}
