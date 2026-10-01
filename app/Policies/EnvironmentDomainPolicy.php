<?php

namespace App\Policies;

use App\Models\EnvironmentDomain;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasRolePolicies;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnvironmentDomainPolicy
{
    use HandlesAuthorization;
    use HasRolePolicies;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->hasReadAccess($user, $project);
    }

    public function view(User $user, EnvironmentDomain $model): bool
    {
        return $this->hasReadAccess($user, $model->project());
    }

    public function create(User $user, Project $project): bool
    {
        return $this->hasWriteAccess($user, $project);
    }

    public function update(User $user, EnvironmentDomain $model): bool
    {
        return $this->hasWriteAccess($user, $model->project());
    }

    public function delete(User $user, EnvironmentDomain $model): bool
    {
        return $this->hasWriteAccess($user, $model->project());
    }
}
