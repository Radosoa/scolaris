<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/** Affectations : un professeur ne liste que les siennes (filtre appliqué par le contrôleur). */
class AffectationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministration() || $user->hasRole(Role::Professeur->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AffectationsGerer->value);
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
