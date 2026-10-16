<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Matiere;
use Illuminate\Validation\ValidationException;

class AffectationService
{
    /**
     * @param  array{enseignant_id: int, matiere_id: int, classe_id: int}  $donnees
     */
    public function enregistrer(array $donnees, ?Affectation $affectation = null): Affectation
    {
        $this->verifierMatiereEnseignee($donnees['matiere_id'], $donnees['classe_id']);

        $affectation ??= new Affectation;
        $affectation->fill($donnees)->save();

        return $affectation;
    }

    /** La matière doit avoir un coefficient au niveau de la classe, sinon elle n'y est pas enseignée. */
    private function verifierMatiereEnseignee(int $matiereId, int $classeId): void
    {
        $classe = Classe::with('niveau')->findOrFail($classeId);
        $enseignee = $classe->niveau->coefficients()->where('matiere_id', $matiereId)->exists();

        if (! $enseignee) {
            $matiere = Matiere::findOrFail($matiereId);
            throw ValidationException::withMessages([
                'matiere_id' => "{$matiere->nom} n'est pas enseignée en {$classe->niveau->libelle} : définissez d'abord son coefficient.",
            ]);
        }
    }
}
