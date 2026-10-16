<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Annonce
 */
class AnnonceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'contenu' => $this->contenu,
            'cible' => $this->cible->value,
            'cible_libelle' => $this->cible->libelle(),
            'classe' => $this->whenLoaded('classe', fn () => $this->classe ? ['id' => $this->classe->id, 'nom' => $this->classe->nom] : null),
            'auteur' => $this->whenLoaded('auteur', fn () => $this->auteur->name),
            'date_publication' => $this->date_publication->toIso8601String(),
            'publiee' => $this->estPubliee(),
            'modifiable' => $user ? $user->can('update', $this->resource) : false,
        ];
    }
}
