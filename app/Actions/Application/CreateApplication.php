<?php

namespace App\Actions\Application;

use App\Actions\Environment\CreateEnvironment;
use App\Enums\ApplicationStatus;
use App\Exceptions\RepositoryNotFound;
use App\Exceptions\RepositoryPermissionDenied;
use App\Exceptions\SourceControlIsNotConnected;
use App\Models\Application;
use App\Models\Project;
use App\Models\SourceControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateApplication
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(Project $project, array $input): Application
    {
        $this->validate($project, $input);

        $application = new Application([
            'project_id' => $project->id,
            'name' => $input['name'],
            'source_control_id' => $input['source_control_id'] ?? null,
            'repository' => $input['repository'] ?? null,
            'status' => ApplicationStatus::READY,
        ]);

        $this->assertRepositoryIsReadable($application);

        $productionBranch = trim((string) ($input['production_branch'] ?? ''));

        return DB::transaction(function () use ($application, $productionBranch): Application {
            $application->save();

            if ($productionBranch !== '') {
                app(CreateEnvironment::class)->create($application, [
                    'name' => 'Production',
                    'branch' => $productionBranch,
                ]);
            }

            return $application;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function validate(Project $project, array $input): void
    {
        Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('applications', 'name')->where('project_id', $project->id),
            ],
            'source_control_id' => [
                'nullable',
                Rule::exists('source_controls', 'id'),
            ],
            'repository' => [
                'nullable',
                'string',
                'max:255',
                'required_with:source_control_id',
            ],
            'production_branch' => [
                'nullable',
                'string',
                'max:255',
            ],
        ])->validate();
    }

    /**
     * @throws ValidationException
     */
    private function assertRepositoryIsReadable(Application $application): void
    {
        if ($application->source_control_id === null || $application->repository === null) {
            return;
        }

        /** @var ?SourceControl $sourceControl */
        $sourceControl = SourceControl::query()->find($application->source_control_id);

        if ($sourceControl === null) {
            return;
        }

        try {
            $sourceControl->getRepo($application->repository);
        } catch (SourceControlIsNotConnected) {
            throw ValidationException::withMessages([
                'source_control_id' => 'Source control is not connected',
            ]);
        } catch (RepositoryPermissionDenied) {
            throw ValidationException::withMessages([
                'repository' => 'You do not have permission to access this repository',
            ]);
        } catch (RepositoryNotFound) {
            throw ValidationException::withMessages([
                'repository' => 'Repository not found',
            ]);
        }
    }
}
