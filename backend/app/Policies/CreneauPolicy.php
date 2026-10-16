<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Creneau;
use App\Models\User;

/**
 * Emploi du temps et appel.
 * - la direction construit l'emploi du temps ;
 * - un professeur fait l'appel de ses propres créneaux uniquement.
 * La consultation (qui voit quel emploi du temps) est filtrée par EmploiDuTempsService.
 */
class CreneauPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::EmploiDuTempsGerer->value);
    }

    public function update(User $user): bool
    {
        return $this->create($user);
    }

    public function delete(User $user): bool
    {
        return $this->create($user);
    }

    public function faireAppel(User $user, Creneau $creneau): bool
    {
        if (! $user->can(Permission::AbsencesSaisir->value)) {
            return false;
        }

        return $user->hasRole(Role::Admin->value)
            || ($user->enseignant !== null && $creneau->affectation->enseignant_id === $user->enseignant->id);
    }
}
