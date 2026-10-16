<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inscription
 */
class InscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'date_inscription' => $this->date_inscription->toDateString(),
            'eleve_id' => $this->eleve_id,
            'classe_id' => $this->classe_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'eleve' => $this->whenLoaded('eleve', fn () => [
                'id' => $this->eleve->id,
                'matricule' => $this->eleve->matricule,
                'nom_complet' => $this->eleve->nomComplet(),
                'sexe' => $this->eleve->sexe->value,
            ]),
            'classe' => $this->whenLoaded('classe', fn () => ['id' => $this->classe->id, 'nom' => $this->classe->nom]),
            'annee_scolaire' => $this->whenLoaded('anneeScolaire', fn () => [
                'id' => $this->anneeScolaire->id,
                'libelle' => $this->anneeScolaire->libelle,
                'active' => $this->anneeScolaire->active,
            ]),
        ];
    }
}
