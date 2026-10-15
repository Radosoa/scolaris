<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Classe
 */
class ClasseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'capacite' => $this->capacite,
            'effectif' => $this->whenHas('effectif', fn ($effectif) => (int) $effectif),
            'niveau_id' => $this->niveau_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'niveau' => new NiveauResource($this->whenLoaded('niveau')),
            'annee_scolaire' => $this->whenLoaded('anneeScolaire', fn () => [
                'id' => $this->anneeScolaire->id,
                'libelle' => $this->anneeScolaire->libelle,
            ]),
        ];
    }
}
