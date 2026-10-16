<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\User;

/**
 * Évaluations et notes : un professeur n'agit que sur ses propres affectations.
 * La direction a un accès complet (elle détient toutes les permissions).
 * Le verrouillage d'une période clôturée est une règle métier (EvaluationService, 409).
 */
class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Professeur->value]);
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $this->estResponsable($user, $evaluation->affectation);
    }

    /** Création d'une évaluation pour une affectation donnée. */
    public function create(User $user, Affectation $affectation): bool
    {
        return $user->can(Permission::NotesSaisir->value) && $this->estResponsable($user, $affectation);
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        return $this->create($user, $evaluation->affectation);
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return $this->update($user, $evaluation);
    }

    /** Un professeur ne note jamais une classe où il n'est pas affecté. */
    private function estResponsable(User $user, Affectation $affectation): bool
    {
        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $user->hasRole(Role::Professeur->value)
            && $user->enseignant !== null
            && $affectation->enseignant_id === $user->enseignant->id;
    }
}
