<?php

namespace App\Policies;

use App\Models\Cause;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CausePolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin ?? false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cause $cause): bool
    {
        return $user->is_admin ?? false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cause $cause): bool
    {
        return $user->is_admin ?? false;
    }
}
