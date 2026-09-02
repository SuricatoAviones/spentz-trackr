<?php

namespace App\Policies;

use App\Models\Income;
use App\Models\User;

class IncomePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Income $income): bool
    {
        return $income->user_id === $user->id;
    }
}
