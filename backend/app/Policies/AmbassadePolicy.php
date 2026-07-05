<?php

namespace App\Policies;

use App\Models\Ambassade;
use App\Models\User;

class AmbassadePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ambassades.view');
    }

    public function view(User $user, Ambassade $ambassade): bool
    {
        if (! $user->hasPermission('ambassades.view')) {
            return false;
        }

        // Agents ambassade : seulement leur propre ambassade
        if ($user->isScopedToAmbassade()) {
            return $user->ambassade_id === $ambassade->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ambassades.create');
    }

    public function update(User $user, Ambassade $ambassade): bool
    {
        return $user->hasPermission('ambassades.update');
    }

    public function delete(User $user, Ambassade $ambassade): bool
    {
        // Pas de permission dédiée : super_admin uniquement + pas de lots
        return $user->isSuperAdmin() && $ambassade->lots()->doesntExist();
    }
}
