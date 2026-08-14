<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Version;

class VersionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Version $version): bool
    {
        $ownerId = $version->project?->user_id;

        return $ownerId !== null && $user->id === $ownerId;
    }

    public function create(User $user, Version $version): bool
    {
        return $this->view($user, $version);
    }

    public function update(User $user, Version $version): bool
    {
        return $this->view($user, $version);
    }

    public function delete(User $user, Version $version): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $this->view($user, $version);
    }
}
