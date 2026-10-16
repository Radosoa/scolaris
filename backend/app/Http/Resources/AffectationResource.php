<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Affectation
 */
class AffectationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enseignant_id' => $this->enseignant_id,
            'matiere_id' => $this->matiere_id,
            'classe_id' => $this->classe_id,
            'enseignant' => $this->whenLoaded('enseignant', fn () => [
                'id' => $this->enseignant->id,
                'nom_complet' => $this->enseignant->nomComplet(),
            ]),
            'matiere' => $this->whenLoaded('matiere', fn () => [
                'id' => $this->matiere->id,
                'nom' => $this->matiere->nom,
                'code' => $this->matiere->code,
            ]),
            'classe' => $this->whenLoaded('classe', fn () => [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
                'annee_scolaire_id' => $this->classe->annee_scolaire_id,
            ]),
        ];
    }
}
