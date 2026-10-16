<?php

namespace App\Models;

use App\Enums\StatutInscription;
use App\Policies\InscriptionPolicy;
use Database\Factories\InscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('inscriptions')]
#[Fillable(['eleve_id', 'classe_id', 'annee_scolaire_id', 'statut', 'date_inscription'])]
#[UsePolicy(InscriptionPolicy::class)]
class Inscription extends Model
{
    /** @use HasFactory<InscriptionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'inscrit'];

    protected function casts(): array
    {
        return [
            'statut' => StatutInscription::class,
            'date_inscription' => 'date',
        ];
    }

    /** @return BelongsTo<Eleve, $this> */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class)->withTrashed();
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /** @return HasMany<Paiement, $this> */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /** @return HasMany<Bulletin, $this> */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }
}
