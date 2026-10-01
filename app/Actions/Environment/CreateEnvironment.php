<?php

namespace App\Actions\Environment;

use App\Enums\EnvironmentStatus;
use App\Models\Application;
use App\Models\Environment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateEnvironment
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(Application $application, array $input): Environment
    {
        $this->validate($application, $input);

        $slug = $this->slug($application, (string) $input['name']);

        return DB::transaction(function () use ($application, $input, $slug): Environment {
            $environment = new Environment([
                'application_id' => $application->id,
                'name' => $input['name'],
                'slug' => $slug,
                'branch' => $input['branch'] ?? null,
                'color' => $input['color'] ?? 'slate',
                'auto_deploy' => (bool) ($input['auto_deploy'] ?? false),
                'status' => EnvironmentStatus::READY,
            ]);
            $environment->save();

            foreach (['build', 'pre-flight'] as $name) {
                $environment->deploymentScripts()->create([
                    'name' => $name,
                    'content' => '',
                ]);
            }

            return $environment;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Application $application, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('environments', 'name')->where('application_id', $application->id),
            ],
            'branch' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', Rule::in(config('core.colors'))],
            'auto_deploy' => ['nullable', 'boolean'],
        ])->validate();
    }

    /**
     * The slug composes on-disk paths, so it is derived once and never changes.
     *
     * @throws ValidationException
     */
    private function slug(Application $application, string $name): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'The name must contain at least one alphanumeric character.',
            ]);
        }

        $exists = $application->environments()->where('slug', $slug)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'An environment with a matching slug already exists.',
            ]);
        }

        return $slug;
    }
}
