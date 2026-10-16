<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Eleve;
use App\Models\User;

/**
 * Dossiers élèves.
 * - direction / secrétariat : tous les élèves ;
 * - professeur : uniquement les élèves inscrits dans une classe où il enseigne ;
 * - parent : uniquement ses enfants ;
 * - élève : uniquement son propre dossier.
 */
class ElevePolicy
{
    public function viewAny(User $user): bool
    {
        // La liste d'un professeur est restreinte à ses classes par EleveService::visiblesPar()
        return $user->estAdministration() || $user->hasRole(Role::Professeur->value);
    }

    public function view(User $user, Eleve $eleve): bool
    {
        if ($user->estAdministration()) {
            return true;
        }

        if ($user->hasRole(Role::Professeur->value) && $user->enseignant) {
            return $eleve->inscriptions()->whereIn('classe_id', $user->enseignant->classeIds())->exists();
        }

        if ($user->hasRole(Role::Parent->value) && $user->tuteur) {
            return $user->tuteur->eleves()->whereKey($eleve->id)->exists();
        }

        if ($user->hasRole(Role::Eleve->value)) {
            return $eleve->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ElevesGerer->value);
    }

    public function update(User $user): bool
    {
        return $this->create($user);
    }

    public function delete(User $user): bool
    {
        return $this->create($user);
    }
}
