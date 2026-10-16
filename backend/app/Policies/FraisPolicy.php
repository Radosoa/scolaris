<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/** Frais et échéancier : fixés par la direction, consultés par le secrétariat. */
class FraisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaiementsGerer->value) || $user->can(Permission::StructureGerer->value);
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
