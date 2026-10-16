<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Suivi des absences par la vie scolaire (secrétariat, direction).
 * Un parent ou un élève consulte les absences via /eleves/{eleve}/absences (ElevePolicy).
 */
class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AbsencesJustifier->value);
    }

    public function justifier(User $user): bool
    {
        return $this->viewAny($user);
    }
}
