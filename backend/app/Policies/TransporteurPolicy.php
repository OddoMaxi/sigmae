<?php

namespace App\Policies;

use App\Models\Transporteur;
use App\Models\User;

class TransporteurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('transporteurs.view');
    }

    public function view(User $user, Transporteur $transporteur): bool
    {
        return $user->hasPermission('transporteurs.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('transporteurs.create');
    }

    public function update(User $user, Transporteur $transporteur): bool
    {
        return $user->hasPermission('transporteurs.update');
    }

    public function delete(User $user, Transporteur $transporteur): bool
    {
        return $user->hasPermission('transporteurs.delete') && $transporteur->lots()->doesntExist();
    }
}
