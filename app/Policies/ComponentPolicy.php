<?php

namespace App\Policies;

use App\Models\Component;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasRolePolicies;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComponentPolicy
{
    use HandlesAuthorization;
    use HasRolePolicies;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->hasReadAccess($user, $project);
    }

    public function view(User $user, Component $component): bool
    {
        return $this->hasReadAccess($user, $component->project());
    }

    public function create(User $user, Project $project): bool
    {
        return $this->hasWriteAccess($user, $project);
    }

    public function update(User $user, Component $component): bool
    {
        return $this->hasWriteAccess($user, $component->project());
    }

    public function delete(User $user, Component $component): bool
    {
        return $this->hasWriteAccess($user, $component->project());
    }
}
