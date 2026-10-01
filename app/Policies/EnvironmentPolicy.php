<?php

namespace App\Policies;

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasRolePolicies;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnvironmentPolicy
{
    use HandlesAuthorization;
    use HasRolePolicies;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->hasReadAccess($user, $project);
    }

    public function view(User $user, Environment $environment): bool
    {
        return $this->hasReadAccess($user, $environment->project());
    }

    public function create(User $user, Project $project): bool
    {
        return $this->hasWriteAccess($user, $project);
    }

    public function update(User $user, Environment $environment): bool
    {
        return $this->hasWriteAccess($user, $environment->project());
    }

    public function delete(User $user, Environment $environment): bool
    {
        return $this->hasWriteAccess($user, $environment->project());
    }
}
