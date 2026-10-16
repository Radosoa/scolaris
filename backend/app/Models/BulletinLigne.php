<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne « matière » d'un bulletin. Le coefficient est recopié depuis le niveau au moment
 * du calcul : un bulletin validé ne change pas si les coefficients évoluent ensuite.
 * L'appréciation est saisie par le professeur de la matière.
 */
#[Table('bulletin_lignes')]
#[Fillable(['bulletin_id', 'matiere_id', 'moyenne', 'coefficient', 'rang_matiere', 'moyenne_classe', 'moyenne_max', 'moyenne_min'])]
class BulletinLigne extends Model
{
    protected function casts(): array
    {
        return [
            'moyenne' => 'float',
            'coefficient' => 'float',
            'rang_matiere' => 'integer',
            'moyenne_classe' => 'float',
            'moyenne_max' => 'float',
            'moyenne_min' => 'float',
        ];
    }

    /** @return BelongsTo<Bulletin, $this> */
    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    /** @return BelongsTo<Matiere, $this> */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
