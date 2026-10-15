<?php

namespace App\Services;

use App\Models\Coefficient;
use App\Models\Matiere;
use App\Models\Niveau;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoefficientService
{
    /**
     * Toutes les matières avec leur coefficient pour ce niveau (null si la matière n'est pas enseignée).
     *
     * @return Collection<int, array{matiere_id: int, nom: string, code: string, valeur: float|null}>
     */
    public function grille(Niveau $niveau): Collection
    {
        $coefficients = $niveau->coefficients()->pluck('valeur', 'matiere_id');

        return Matiere::orderBy('nom')->get()->map(fn (Matiere $matiere) => [
            'matiere_id' => $matiere->id,
            'nom' => $matiere->nom,
            'code' => $matiere->code,
            'valeur' => isset($coefficients[$matiere->id]) ? (float) $coefficients[$matiere->id] : null,
        ]);
    }

    /**
     * Enregistre la grille : une valeur crée ou met à jour le coefficient,
     * null le supprime (la matière n'est plus enseignée à ce niveau).
     *
     * @param  list<array{matiere_id: int, valeur: float|null}>  $lignes
     */
    public function synchroniser(Niveau $niveau, array $lignes): void
    {
        DB::transaction(function () use ($niveau, $lignes) {
            foreach ($lignes as $ligne) {
                if ($ligne['valeur'] === null) {
                    $niveau->coefficients()->where('matiere_id', $ligne['matiere_id'])->delete();

                    continue;
                }

                Coefficient::updateOrCreate(
                    ['niveau_id' => $niveau->id, 'matiere_id' => $ligne['matiere_id']],
                    ['valeur' => $ligne['valeur']],
                );
            }
        });
    }
}
