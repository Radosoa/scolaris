<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Eleve;
use App\Models\Paiement;
use App\Models\User;

/**
 * Paiements : encaissement, annulation et journal par le secrétariat (et la direction) ;
 * un parent consulte la situation et les reçus de ses enfants uniquement.
 */
class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaiementsGerer->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function annuler(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Reçu d'un paiement. */
    public function view(User $user, Paiement $paiement): bool
    {
        return $this->viewAny($user) || $this->estParentDe($user, $paiement->inscription->eleve_id);
    }

    /** Situation financière d'un élève (frais, échéances, paiements). */
    public function voirSituation(User $user, Eleve $eleve): bool
    {
        return $this->viewAny($user) || $this->estParentDe($user, $eleve->id);
    }

    private function estParentDe(User $user, int $eleveId): bool
    {
        return $user->hasRole(Role::Parent->value)
            && $user->tuteur !== null
            && $user->tuteur->eleves()->whereKey($eleveId)->exists();
    }
}
