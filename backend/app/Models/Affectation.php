<?php

namespace App\Models;

use App\Policies\AffectationPolicy;
use Database\Factories\AffectationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un enseignant enseigne une matière dans une classe (l'année est celle de la classe). */
#[Table('affectations')]
#[Fillable(['enseignant_id', 'matiere_id', 'classe_id'])]
#[UsePolicy(AffectationPolicy::class)]
class Affectation extends Model
{
    /** @use HasFactory<AffectationFactory> */
    use HasFactory;

    /** @return BelongsTo<Enseignant, $this> */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class)->withTrashed();
    }

    /** @return BelongsTo<Matiere, $this> */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** @return HasMany<Creneau, $this> */
    public function creneaux(): HasMany
    {
        return $this->hasMany(Creneau::class);
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
