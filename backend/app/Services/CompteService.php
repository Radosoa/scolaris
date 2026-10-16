<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\RegleMetierException;
use App\Models\Eleve;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Création des comptes de connexion rattachés aux profils élève et parent. */
class CompteService
{
    public function creerPourEleve(Eleve $eleve, string $email, string $motDePasse): User
    {
        return $this->creer($eleve, $eleve->nomComplet(), $email, $motDePasse, Role::Eleve);
    }

    public function creerPourTuteur(Tuteur $tuteur, string $email, string $motDePasse): User
    {
        return $this->creer($tuteur, $tuteur->nomComplet(), $email, $motDePasse, Role::Parent);
    }

    public function creerUtilisateur(string $nom, string $email, string $motDePasse, Role $role): User
    {
        return User::create([
            'name' => $nom,
            'email' => $email,
            'password' => $motDePasse,
            'actif' => true,
        ])->assignRole($role->value);
    }

    private function creer(Eleve|Tuteur $profil, string $nom, string $email, string $motDePasse, Role $role): User
    {
        if ($profil->user_id) {
            throw new RegleMetierException('Ce profil possède déjà un compte de connexion.');
        }

        return DB::transaction(function () use ($profil, $nom, $email, $motDePasse, $role) {
            $user = $this->creerUtilisateur($nom, $email, $motDePasse, $role);
            $profil->user()->associate($user)->save();

            return $user;
        });
    }
}
