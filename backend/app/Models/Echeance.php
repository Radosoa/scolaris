<?php

namespace App\Models;

use App\Policies\FraisPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tranche de l'échéancier : part des frais (en %) à régler avant la date limite. */
#[Table('echeances')]
#[Fillable(['annee_scolaire_id', 'libelle', 'date_limite', 'pourcentage'])]
#[UsePolicy(FraisPolicy::class)]
class Echeance extends Model
{
    protected function casts(): array
    {
        return [
            'date_limite' => 'date',
            'pourcentage' => 'float',
        ];
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
