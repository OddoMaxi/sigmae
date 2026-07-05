<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        // Chacun peut modifier son propre profil (name, password)
        // Les admins peuvent tout modifier
        return $user->hasPermission('users.update') || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('users.delete') && $user->id !== $model->id;
    }

    public function toggleStatus(User $user, User $model): bool
    {
        return $user->hasPermission('users.toggle_status') && $user->id !== $model->id;
    }
}
