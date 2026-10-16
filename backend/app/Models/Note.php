<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'un élève à une évaluation. Un élève absent a `absent = true` et `valeur = null` :
 * la note n'est alors comptée ni comme zéro ni dans le diviseur (règle du bulletin).
 */
#[Table('notes')]
#[Fillable(['evaluation_id', 'eleve_id', 'valeur', 'absent'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['absent' => false];

    protected function casts(): array
    {
        return [
            'valeur' => 'float',
            'absent' => 'boolean',
        ];
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<Eleve, $this> */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class)->withTrashed();
    }
}
