<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Enseignant;
use App\Models\User;

/** Enseignants : gérés par la direction, consultables par le secrétariat. */
class EnseignantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministration();
    }

    public function view(User $user, Enseignant $enseignant): bool
    {
        return $user->estAdministration() || $enseignant->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EnseignantsGerer->value);
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
