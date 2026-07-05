<?php

namespace App\Policies;

use App\Models\Pays;
use App\Models\User;

class PaysPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pays.view');
    }

    public function view(User $user, Pays $pays): bool
    {
        return $user->hasPermission('pays.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pays.create');
    }

    public function update(User $user, Pays $pays): bool
    {
        return $user->hasPermission('pays.update');
    }

    public function delete(User $user, Pays $pays): bool
    {
        return $user->hasPermission('pays.delete') && $pays->ambassades()->doesntExist();
    }
}
