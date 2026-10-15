<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/**
 * Structure académique (années, périodes, niveaux, classes, matières, coefficients).
 * Consultable par le personnel, modifiable uniquement par la direction.
 */
class StructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Secretariat->value, Role::Professeur->value]);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StructureGerer->value);
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
