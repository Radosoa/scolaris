<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Annonce;
use App\Models\User;

/**
 * Annonces : publiées par la direction et le secrétariat ; une annonce n'est modifiable
 * que par son auteur ou par la direction. La lecture est filtrée par AnnonceService.
 */
class AnnoncePolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::AnnoncesPublier->value);
    }

    public function update(User $user, Annonce $annonce): bool
    {
        return $this->create($user)
            && ($annonce->auteur_id === $user->id || $user->hasRole(Role::Admin->value));
    }

    public function delete(User $user, Annonce $annonce): bool
    {
        return $this->update($user, $annonce);
    }
}
