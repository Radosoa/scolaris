<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnneeScolaireService
{
    /**
     * @param  array{libelle: string, date_debut: string, date_fin: string}  $donnees
     */
    public function creer(array $donnees, bool $activer = false): AnneeScolaire
    {
        return DB::transaction(function () use ($donnees, $activer) {
            $annee = AnneeScolaire::create($donnees);

            return $activer ? $this->activer($annee) : $annee;
        });
    }

    /**
     * @param  array{libelle?: string, date_debut?: string, date_fin?: string}  $donnees
     */
    public function modifier(AnneeScolaire $annee, array $donnees): AnneeScolaire
    {
        $annee->fill($donnees);

        // Les périodes existantes doivent rester comprises dans l'année
        if ($annee->isDirty(['date_debut', 'date_fin'])) {
            $hors = $annee->periodes()
                ->where(fn ($q) => $q->where('date_debut', '<', $annee->date_debut)
                    ->orWhere('date_fin', '>', $annee->date_fin))
                ->exists();

            if ($hors) {
                throw ValidationException::withMessages([
                    'date_debut' => 'Certaines périodes sortiraient des nouvelles dates de l\'année.',
                ]);
            }
        }

        $annee->save();

        return $annee;
    }

    /**
     * Active l'année et désactive toutes les autres : une seule année active à la fois.
     */
    public function activer(AnneeScolaire $annee): AnneeScolaire
    {
        DB::transaction(function () use ($annee) {
            // Verrou pour sérialiser deux activations simultanées
            AnneeScolaire::query()->lockForUpdate()->get(['id']);
            AnneeScolaire::query()->whereKeyNot($annee->id)->update(['active' => false]);
            $annee->forceFill(['active' => true])->save();
        });

        return $annee;
    }

    public function supprimer(AnneeScolaire $annee): void
    {
        if ($annee->active) {
            throw new RegleMetierException('Impossible de supprimer l\'année scolaire active.');
        }

        $annee->delete();
    }

    public function anneeActive(): ?AnneeScolaire
    {
        return AnneeScolaire::active()->first();
    }
}
