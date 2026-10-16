<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class InscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministration();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InscriptionsGerer->value);
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
