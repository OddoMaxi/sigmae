<?php

namespace App\Policies;

use App\Models\Passeport;
use App\Models\User;

class PasseportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('passeports.view');
    }

    public function view(User $user, Passeport $passeport): bool
    {
        if (! $user->hasPermission('passeports.view')) {
            return false;
        }

        // Agents ambassade : seulement les passeports dans leurs lots
        if ($user->isScopedToAmbassade()) {
            return $passeport->lot?->ambassade_id === $user->ambassade_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('passeports.create');
    }

    public function update(User $user, Passeport $passeport): bool
    {
        if (! $user->hasPermission('passeports.update')) {
            return false;
        }

        // Passeport expédié ou livré : seul super_admin / admin_central peut modifier
        if (in_array($passeport->statut, ['expedie', 'livre'])) {
            return $user->isAdminCentral();
        }

        return true;
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('passeports.import');
    }
}
