<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'member'], true) && $user->status === 'active';
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->id === $project->user_id;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->id === $project->user_id;
    }
}
