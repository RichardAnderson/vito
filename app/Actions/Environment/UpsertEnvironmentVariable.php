<?php

namespace App\Actions\Environment;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpsertEnvironmentVariable
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function upsert(Environment $environment, array $input, ?EnvironmentVariable $existing = null): EnvironmentVariable
    {
        $this->validate($environment, $input, $existing);

        $componentId = isset($input['component_id']) && $input['component_id'] ? (int) $input['component_id'] : null;
        $isSecret = (bool) ($input['is_secret'] ?? false);
        $value = (string) ($input['value'] ?? '');

        return DB::transaction(function () use ($environment, $input, $existing, $componentId, $isSecret, $value): EnvironmentVariable {
            $variable = $existing ?? new EnvironmentVariable([
                'environment_id' => $environment->id,
                'component_id' => $componentId,
            ]);

            $variable->key = (string) $input['key'];
            $variable->is_secret = $isSecret || ($existing !== null && $existing->is_secret);

            // An empty submission against an existing secret means "keep it", never "blank it".
            if (! ($value === '' && $existing !== null && $existing->is_secret)) {
                $variable->value = $value;
            }

            $variable->save();

            $this->markAffectedComponentsForRedeploy($environment, $componentId);

            return $variable;
        });
    }

    private function markAffectedComponentsForRedeploy(Environment $environment, ?int $componentId): void
    {
        $environment->components()
            ->when($componentId !== null, fn ($query) => $query->whereKey($componentId))
            ->update(['needs_redeploy' => true]);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Environment $environment, array $input, ?EnvironmentVariable $existing): void
    {
        $componentId = isset($input['component_id']) && $input['component_id'] ? (int) $input['component_id'] : null;

        $uniqueRule = Rule::unique('environment_variables', 'key')
            ->where('environment_id', $environment->id);

        // A unique index treats NULLs as distinct on every engine Vito supports, so the
        // environment-level scope is guarded here rather than by the index.
        $uniqueRule = $componentId === null
            ? $uniqueRule->whereNull('component_id')
            : $uniqueRule->where('component_id', $componentId);

        if ($existing !== null) {
            $uniqueRule = $uniqueRule->ignore($existing->id);
        }

        Validator::make($input, [
            'key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/', $uniqueRule],
            'value' => ['nullable', 'string'],
            'is_secret' => ['nullable', 'boolean'],
            'component_id' => [
                'nullable',
                Rule::exists('components', 'id')->where('environment_id', $environment->id),
            ],
        ])->validate();
    }
}
