<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InscriptionService
{
    /**
     * Inscrit l'élève dans une classe de l'année active, si une place est disponible.
     */
    public function inscrire(Eleve $eleve, Classe $classe, ?string $date = null): Inscription
    {
        return DB::transaction(function () use ($eleve, $classe, $date) {
            $classe = $this->verrouiller($classe);

            if (! $classe->anneeScolaire->active) {
                throw new RegleMetierException('Les inscriptions se font uniquement sur l\'année scolaire active.');
            }

            $existante = $eleve->inscriptions()->where('annee_scolaire_id', $classe->annee_scolaire_id)->with('classe')->first();
            if ($existante) {
                throw ValidationException::withMessages([
                    'eleve_id' => "Cet élève est déjà inscrit en {$existante->classe->nom} pour {$classe->anneeScolaire->libelle}.",
                ]);
            }

            $this->verifierPlace($classe);

            return Inscription::create([
                'eleve_id' => $eleve->id,
                'classe_id' => $classe->id,
                'annee_scolaire_id' => $classe->annee_scolaire_id,
                'statut' => StatutInscription::Inscrit,
                'date_inscription' => $date ?? now()->toDateString(),
            ]);
        });
    }

    /**
     * Change de classe (dans la même année) et/ou de statut. Revenir au statut « inscrit »
     * ou changer de classe nécessite une place libre.
     */
    public function modifier(Inscription $inscription, ?int $classeId, ?StatutInscription $statut): Inscription
    {
        return DB::transaction(function () use ($inscription, $classeId, $statut) {
            $statut ??= $inscription->statut;
            $classe = $this->verrouiller(Classe::findOrFail($classeId ?? $inscription->classe_id));

            if ($classe->annee_scolaire_id !== $inscription->annee_scolaire_id) {
                throw ValidationException::withMessages([
                    'classe_id' => 'La nouvelle classe doit appartenir à la même année scolaire.',
                ]);
            }

            $occupeUnePlace = $statut === StatutInscription::Inscrit;
            $occupaitCettePlace = $inscription->statut === StatutInscription::Inscrit && $inscription->classe_id === $classe->id;

            if ($occupeUnePlace && ! $occupaitCettePlace) {
                $this->verifierPlace($classe);
            }

            $inscription->update(['classe_id' => $classe->id, 'statut' => $statut]);

            return $inscription;
        });
    }

    /** Verrou sur la classe : deux inscriptions simultanées ne peuvent pas dépasser la capacité. */
    private function verrouiller(Classe $classe): Classe
    {
        return Classe::with('anneeScolaire')->lockForUpdate()->findOrFail($classe->id);
    }

    private function verifierPlace(Classe $classe): void
    {
        $effectif = $classe->effectif();

        if ($effectif >= $classe->capacite) {
            throw ValidationException::withMessages([
                'classe_id' => "La classe {$classe->nom} est complète ({$effectif}/{$classe->capacite}).",
            ]);
        }
    }
}
