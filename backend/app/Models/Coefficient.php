<?php

namespace App\Models;

use App\Policies\StructurePolicy;
use Database\Factories\CoefficientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('coefficients')]
#[Fillable(['niveau_id', 'matiere_id', 'valeur'])]
#[UsePolicy(StructurePolicy::class)]
class Coefficient extends Model
{
    /** @use HasFactory<CoefficientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['valeur' => 'float'];
    }

    /** @return BelongsTo<Niveau, $this> */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /** @return BelongsTo<Matiere, $this> */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
