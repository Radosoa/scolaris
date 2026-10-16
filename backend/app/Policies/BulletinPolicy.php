<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Affectation;
use App\Models\Bulletin;
use App\Models\User;

/**
 * Bulletins.
 * - direction : calcul, appréciation générale, validation, PDF ; voit aussi les brouillons ;
 * - secrétariat : bulletins validés de tous les élèves (édition de documents) ;
 * - professeur : bulletins validés de ses classes ; appréciations de ses matières ;
 * - parent : bulletins validés de ses enfants ;
 * - élève : ses propres bulletins validés.
 */
class BulletinPolicy
{
    /** Écran de la direction : bulletins d'une classe, calcul, validation, PDF de classe. */
    public function gerer(User $user): bool
    {
        return $user->can(Permission::BulletinsGenerer->value);
    }

    public function valider(User $user): bool
    {
        return $user->can(Permission::BulletinsValider->value);
    }

    public function view(User $user, Bulletin $bulletin): bool
    {
        if ($this->gerer($user)) {
            return true;
        }

        // Un brouillon n'est visible que de la direction
        if (! $bulletin->valide) {
            return false;
        }

        $inscription = $bulletin->inscription;

        return match (true) {
            $user->hasRole(Role::Secretariat->value) => true,
            $user->hasRole(Role::Professeur->value) => $user->enseignant !== null
                && in_array($inscription->classe_id, $user->enseignant->classeIds()),
            $user->hasRole(Role::Parent->value) => $user->tuteur !== null
                && $user->tuteur->eleves()->whereKey($inscription->eleve_id)->exists(),
            $user->hasRole(Role::Eleve->value) => $user->eleve?->id === $inscription->eleve_id,
            default => false,
        };
    }

    /** Appréciation générale : direction, bulletin non validé (règle vérifiée par le service). */
    public function update(User $user): bool
    {
        return $this->gerer($user);
    }

    /** Appréciations d'une matière : le professeur de l'affectation (ou la direction). */
    public function saisirAppreciations(User $user, Affectation $affectation): bool
    {
        if (! $user->can(Permission::AppreciationsSaisir->value)) {
            return false;
        }

        return $user->hasRole(Role::Admin->value)
            || ($user->enseignant !== null && $affectation->enseignant_id === $user->enseignant->id);
    }
}
