<?php

namespace App\Actions\Application;

use App\Models\Application;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateApplication
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(Application $application, array $input): Application
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('applications', 'name')
                    ->where('project_id', $application->project_id)
                    ->ignore($application->id),
            ],
            'repository' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $application->fill([
            'name' => $input['name'],
            'repository' => $input['repository'] ?? $application->repository,
        ])->save();

        return $application;
    }
}
