<?php

namespace App\Models;

use App\Enums\TypeEvaluation;
use App\Policies\EvaluationPolicy;
use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('evaluations')]
#[Fillable(['affectation_id', 'periode_id', 'type', 'titre', 'date', 'bareme'])]
#[UsePolicy(EvaluationPolicy::class)]
class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TypeEvaluation::class,
            'date' => 'date',
            'bareme' => 'float',
        ];
    }

    /** @return BelongsTo<Affectation, $this> */
    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** Les notes ne sont plus modifiables une fois la période clôturée. */
    public function estVerrouillee(): bool
    {
        return (bool) $this->periode->cloturee;
    }
}
