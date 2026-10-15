<?php

namespace App\Models;

use App\Policies\StructurePolicy;
use Database\Factories\PeriodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('periodes')]
#[Fillable(['annee_scolaire_id', 'libelle', 'ordre', 'date_debut', 'date_fin'])]
#[UsePolicy(StructurePolicy::class)]
class Periode extends Model
{
    /** @use HasFactory<PeriodeFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['cloturee' => false];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'cloturee' => 'boolean',
        ];
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
