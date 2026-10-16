<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Evaluation
 */
class EvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'affectation_id' => $this->affectation_id,
            'periode_id' => $this->periode_id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'titre' => $this->titre,
            'date' => $this->date->toDateString(),
            'bareme' => $this->bareme,
            'verrouillee' => $this->whenLoaded('periode', fn () => $this->periode->cloturee),
            'periode' => $this->whenLoaded('periode', fn () => ['id' => $this->periode->id, 'libelle' => $this->periode->libelle]),
            'affectation' => new AffectationResource($this->whenLoaded('affectation')),
            // Nombre de notes saisies (y compris absents) et moyenne hors absents
            'notes_count' => $this->whenCounted('notes'),
            'moyenne' => $this->whenAggregated('notes', 'valeur', 'avg', fn ($moyenne) => $moyenne === null ? null : round((float) $moyenne, 2)),
        ];
    }
}
