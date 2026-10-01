<?php

namespace App\Actions\Environment;

use App\Models\Environment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateEnvironment
{
    /**
     * `slug` is deliberately not updatable — on-disk component paths derive from it.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(Environment $environment, array $input): Environment
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('environments', 'name')
                    ->where('application_id', $environment->application_id)
                    ->ignore($environment->id),
            ],
            'branch' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(config('core.colors'))],
            'auto_deploy' => ['nullable', 'boolean'],
            'primary_component_id' => [
                'nullable',
                Rule::exists('components', 'id')->where('environment_id', $environment->id),
            ],
        ])->validate();

        $environment->fill([
            'name' => $input['name'],
            'branch' => $input['branch'] ?? $environment->branch,
            'color' => $input['color'] ?? $environment->color,
            'auto_deploy' => (bool) ($input['auto_deploy'] ?? $environment->auto_deploy),
            'primary_component_id' => $input['primary_component_id'] ?? $environment->primary_component_id,
        ])->save();

        return $environment;
    }
}
