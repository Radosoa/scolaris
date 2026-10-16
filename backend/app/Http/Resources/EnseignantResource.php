<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Enseignant
 */
class EnseignantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->nomComplet(),
            'specialite' => $this->specialite,
            'telephone' => $this->telephone,
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'actif' => $this->whenLoaded('user', fn () => $this->user->actif),
            'affectations_count' => $this->whenCounted('affectations'),
            'affectations' => AffectationResource::collection($this->whenLoaded('affectations')),
        ];
    }
}
