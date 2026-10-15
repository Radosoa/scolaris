<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Un compte de démonstration par rôle (mot de passe : « password »).
 * Les profils élève / parent / enseignant associés arrivent en phase 3.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $comptes = [
            [Role::Admin, 'Claire Martin', 'admin@scolaris.test'],
            [Role::Secretariat, 'Sophie Bernard', 'secretariat@scolaris.test'],
            [Role::Professeur, 'Julien Moreau', 'professeur@scolaris.test'],
            [Role::Eleve, 'Lucas Petit', 'eleve@scolaris.test'],
            [Role::Parent, 'Nathalie Petit', 'parent@scolaris.test'],
        ];

        foreach ($comptes as [$role, $name, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'actif' => true],
            )->syncRoles([$role->value]);
        }
    }
}
