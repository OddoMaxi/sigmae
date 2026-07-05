<?php

namespace App\Policies;

use App\Models\Anomalie;
use App\Models\User;

class AnomaliePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('anomalies.view');
    }

    public function view(User $user, Anomalie $anomalie): bool
    {
        if (! $user->hasPermission('anomalies.view')) {
            return false;
        }

        // Agents ambassade : seulement les anomalies de leurs lots
        if ($user->isScopedToAmbassade()) {
            return $anomalie->lot?->ambassade_id === $user->ambassade_id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('anomalies.create');
    }

    public function update(User $user, Anomalie $anomalie): bool
    {
        if ($user->hasPermission('anomalies.update')) {
            return true;
        }

        // Le signaleur peut modifier sa propre anomalie si elle est encore ouverte
        return $user->id === $anomalie->signale_by && $anomalie->statut === 'ouvert';
    }

    public function resoudre(User $user, Anomalie $anomalie): bool
    {
        return $user->hasPermission('anomalies.resolve');
    }
}
