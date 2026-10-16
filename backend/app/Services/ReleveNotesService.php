<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Eleve;
use App\Models\Periode;
use Illuminate\Support\Collection;

/** Relevé des notes d'un élève sur une période, regroupées par matière (consultation). */
class ReleveNotesService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pour(Eleve $eleve, Periode $periode): Collection
    {
        $inscription = $eleve->inscriptions()->where('annee_scolaire_id', $periode->annee_scolaire_id)->first();
        if (! $inscription) {
            return collect();
        }

        $affectations = Affectation::query()
            ->where('classe_id', $inscription->classe_id)
            ->with([
                'matiere',
                'enseignant',
                'evaluations' => fn ($q) => $q->where('periode_id', $periode->id)
                    ->orderBy('date')
                    ->with(['notes' => fn ($n) => $n->where('eleve_id', $eleve->id)]),
            ])
            ->get()
            ->sortBy('matiere.nom')
            ->values();

        return $affectations->map(fn (Affectation $affectation) => [
            'matiere' => ['id' => $affectation->matiere->id, 'nom' => $affectation->matiere->nom, 'code' => $affectation->matiere->code],
            'enseignant' => $affectation->enseignant->nomComplet(),
            'evaluations' => $affectation->evaluations->map(function ($evaluation) {
                $note = $evaluation->notes->first();

                return [
                    'id' => $evaluation->id,
                    'titre' => $evaluation->titre,
                    'type' => $evaluation->type->value,
                    'type_libelle' => $evaluation->type->libelle(),
                    'date' => $evaluation->date->toDateString(),
                    'bareme' => $evaluation->bareme,
                    'valeur' => $note?->valeur,
                    'absent' => (bool) $note?->absent,
                ];
            })->values(),
        ]);
    }
}
