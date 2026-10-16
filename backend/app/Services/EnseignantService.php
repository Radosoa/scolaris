<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\RegleMetierException;
use App\Models\Enseignant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EnseignantService
{
    public function __construct(private readonly CompteService $comptes) {}

    /**
     * Un enseignant a toujours un compte (rôle professeur) pour saisir ses notes.
     *
     * @param  array{nom: string, prenom: string, specialite?: string|null, telephone?: string|null}  $donnees
     */
    public function creer(array $donnees, string $email, string $motDePasse): Enseignant
    {
        return DB::transaction(function () use ($donnees, $email, $motDePasse) {
            $user = $this->comptes->creerUtilisateur(
                "{$donnees['prenom']} {$donnees['nom']}", $email, $motDePasse, Role::Professeur,
            );

            $enseignant = new Enseignant($donnees);
            $enseignant->user()->associate($user);
            $enseignant->save();

            return $enseignant;
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function modifier(Enseignant $enseignant, array $donnees, string $email): Enseignant
    {
        return DB::transaction(function () use ($enseignant, $donnees, $email) {
            $enseignant->update($donnees);
            $enseignant->user->update(['name' => $enseignant->nomComplet(), 'email' => $email]);

            return $enseignant;
        });
    }

    /**
     * Archive l'enseignant et désactive son compte. Ses affectations passées restent
     * visibles dans l'historique (bulletins), celles de l'année active doivent être retirées avant.
     */
    public function supprimer(Enseignant $enseignant): void
    {
        $affecteCetteAnnee = $enseignant->affectations()
            ->whereHas('classe.anneeScolaire', fn (Builder $q) => $q->where('active', true))
            ->exists();

        if ($affecteCetteAnnee) {
            throw new RegleMetierException('Cet enseignant a des affectations pour l\'année en cours. Retirez-les d\'abord.');
        }

        DB::transaction(function () use ($enseignant) {
            $enseignant->user->forceFill(['actif' => false])->save();
            $enseignant->user->tokens()->delete();
            $enseignant->delete();
        });
    }
}
