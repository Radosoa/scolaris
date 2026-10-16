<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tuteur
 */
class TuteurResource extends JsonResource
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
            'telephone' => $this->telephone,
            'email' => $this->email,
            'eleves_count' => $this->whenCounted('eleves'),
            'compte' => $this->whenLoaded('user', fn () => $this->user ? ['email' => $this->user->email, 'actif' => $this->user->actif] : null),
            'eleves' => $this->whenLoaded('eleves', fn () => $this->eleves->map(fn ($eleve) => [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom_complet' => $eleve->nomComplet(),
                'lien' => $eleve->pivot->lien->value,
                'lien_libelle' => $eleve->pivot->lien->libelle(),
            ])),
        ];
    }
}
