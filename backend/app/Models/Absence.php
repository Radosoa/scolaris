<?php

namespace App\Models;

use App\Enums\TypeAbsence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Absence ou retard d'un élève sur un créneau (saisie lors de l'appel, phase 6). */
#[Table('absences')]
#[Fillable(['eleve_id', 'creneau_id', 'date', 'heure_debut', 'heure_fin', 'type', 'justifiee', 'motif'])]
class Absence extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['justifiee' => false];

    protected function casts(): array
    {
        return [
            'type' => TypeAbsence::class,
            'date' => 'date',
            'justifiee' => 'boolean',
        ];
    }

    /** @return BelongsTo<Eleve, $this> */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class)->withTrashed();
    }

    /** Durée en heures (ex. 08:00 → 09:30 = 1,5). */
    public function dureeEnHeures(): float
    {
        [$hd, $md] = array_map('intval', explode(':', $this->heure_debut));
        [$hf, $mf] = array_map('intval', explode(':', $this->heure_fin));

        return max(0, ($hf * 60 + $mf) - ($hd * 60 + $md)) / 60;
    }
}
