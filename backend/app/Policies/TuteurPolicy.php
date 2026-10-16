<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Tuteur;
use App\Models\User;

/** Parents / tuteurs : gérés par l'administration, un parent ne voit que sa propre fiche. */
class TuteurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministration();
    }

    public function view(User $user, Tuteur $tuteur): bool
    {
        return $user->estAdministration() || $tuteur->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TuteursGerer->value);
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
