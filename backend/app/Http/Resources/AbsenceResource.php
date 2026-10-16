<?php

namespace App\Http\Resources;

use App\Enums\TypeAbsence;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Absence
 */
class AbsenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $affectation = $this->relationLoaded('creneau') ? $this->creneau?->affectation : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'date' => $this->date->toDateString(),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            // Absence : durée en heures ; retard : minutes de retard
            'duree_heures' => $this->type === TypeAbsence::Absence ? round($this->dureeEnHeures(), 2) : null,
            'minutes_retard' => $this->type === TypeAbsence::Retard ? Absence::minutesEntre($this->heure_debut, $this->heure_fin) : null,
            'justifiee' => $this->justifiee,
            'motif' => $this->motif,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'matricule' => $this->eleve->matricule,
                'nom' => $this->eleve->nom,
                'prenom' => $this->eleve->prenom,
            ]),
            'classe' => $affectation ? ['id' => $affectation->classe->id, 'nom' => $affectation->classe->nom] : null,
            'matiere' => $affectation ? $affectation->matiere->nom : null,
            'enseignant' => $affectation ? $affectation->enseignant->nomComplet() : null,
        ];
    }
}
