<?php

namespace App\Actions\Site;

use App\Models\Site;
use App\Models\SiteDeploymentBackup;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateDeploymentBackup
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Site $site, array $input): SiteDeploymentBackup
    {
        $input = $this->validate($site, $input);

        $folders = array_values(array_filter($input['folders'] ?? [], fn ($folder): bool => $folder !== null && $folder !== ''));

        if (empty($folders) && ($input['enabled'] ?? false)) {
            $folders = [$site->path];
        }

        /** @var SiteDeploymentBackup $backup */
        $backup = SiteDeploymentBackup::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'enabled' => (bool) ($input['enabled'] ?? false),
                'folders' => $folders,
                'databases' => array_values(array_map('intval', $input['databases'] ?? [])),
                'storage_id' => $input['storage_id'] ?? null,
                'keep' => (int) ($input['keep'] ?? 5),
            ]
        );

        return $backup;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(Site $site, array $input): array
    {
        $basePath = rtrim($site->basePath(), '/');
        $maxKeep = (int) ($site->type_data['modern_deployment_history'] ?? 10);
        $projectId = $site->server->project_id;

        return Validator::make($input, [
            'enabled' => ['required', 'boolean'],
            'folders' => ['array'],
            'folders.*' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($basePath): void {
                    if (! is_string($value) || ! str_starts_with($value, '/')) {
                        $fail('The folder must be an absolute path.');

                        return;
                    }

                    if (preg_match('/^\/[A-Za-z0-9._\-\/]+$/', $value) !== 1 || str_contains($value, '..')) {
                        $fail('The folder contains invalid characters.');

                        return;
                    }

                    $normalized = rtrim($value, '/');

                    if ($normalized !== $basePath && ! str_starts_with($normalized, $basePath.'/')) {
                        $fail('The folder must be within the site directory.');
                    }
                },
            ],
            'databases' => ['array'],
            'databases.*' => [
                Rule::exists('databases', 'id')->where('server_id', $site->server_id),
            ],
            'storage_id' => [
                Rule::requiredIf((bool) ($input['enabled'] ?? false)),
                'nullable',
                Rule::exists('storage_providers', 'id')->where(function ($query) use ($projectId): void {
                    $query->where('project_id', $projectId)->orWhereNull('project_id');
                }),
            ],
            'keep' => ['required', 'integer', 'min:1', 'max:'.$maxKeep],
        ])->validate();
    }
}
