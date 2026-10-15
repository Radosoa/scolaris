<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Periode
 */
class PeriodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'cloturee' => $this->cloturee,
        ];
    }
}
