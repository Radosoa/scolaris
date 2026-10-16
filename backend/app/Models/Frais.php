<?php

namespace App\Models;

use App\Policies\FraisPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne de frais d'un niveau pour une année (ex. « Scolarité 6e : 1 200 € »). */
#[Table('frais')]
#[Fillable(['niveau_id', 'annee_scolaire_id', 'libelle', 'montant'])]
#[UsePolicy(FraisPolicy::class)]
class Frais extends Model
{
    protected function casts(): array
    {
        return ['montant' => 'decimal:2'];
    }

    /** @return BelongsTo<Niveau, $this> */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
