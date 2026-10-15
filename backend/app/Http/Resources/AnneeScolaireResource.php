<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AnneeScolaire
 */
class AnneeScolaireResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'active' => $this->active,
            'periodes_count' => $this->whenCounted('periodes'),
            'classes_count' => $this->whenCounted('classes'),
            'periodes' => PeriodeResource::collection($this->whenLoaded('periodes')),
        ];
    }
}
