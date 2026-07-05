<?php

namespace App\Policies;

use App\Models\Lot;
use App\Models\User;

class LotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lots.view');
    }

    public function view(User $user, Lot $lot): bool
    {
        if (! $user->hasPermission('lots.view')) {
            return false;
        }

        // Agents ambassade : seulement leurs lots
        if ($user->isScopedToAmbassade()) {
            return $user->ambassade_id === $lot->ambassade_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('lots.create');
    }

    public function update(User $user, Lot $lot): bool
    {
        return $user->hasPermission('lots.update') && $lot->isBrouillon();
    }

    public function delete(User $user, Lot $lot): bool
    {
        return $user->hasPermission('lots.delete') && $lot->isBrouillon();
    }

    public function valider(User $user, Lot $lot): bool
    {
        return $user->hasPermission('lots.validate') && $lot->isBrouillon();
    }

    public function expedier(User $user, Lot $lot): bool
    {
        return $user->hasPermission('lots.ship') && $lot->isValide();
    }

    public function reception(User $user, Lot $lot): bool
    {
        if (! $user->hasPermission('lots.receive')) {
            return false;
        }

        // Agent de l'ambassade destinataire seulement
        if ($user->isScopedToAmbassade()) {
            return $user->ambassade_id === $lot->ambassade_id;
        }

        return true;
    }
}
