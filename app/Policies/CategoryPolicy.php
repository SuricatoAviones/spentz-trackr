<?php

namespace App\Policies;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
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
    public function update(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($category->user_id !== $user->id) {
            return false;
        }

        return $category->type === CategoryType::Expense
            ? $category->expenses()->doesntExist()
            : $category->incomes()->doesntExist();
    }
}
