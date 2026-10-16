<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Policies\PaiementPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versement d'une famille pour une inscription. Le numéro de reçu est unique et jamais
 * réutilisé ; un paiement erroné est annulé (et non supprimé) et sort des totaux.
 */
#[Table('paiements')]
#[Fillable(['inscription_id', 'montant', 'date', 'mode', 'reference'])]
#[UsePolicy(PaiementPolicy::class)]
class Paiement extends Model
{
    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date' => 'date',
            'mode' => ModePaiement::class,
            'annule_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inscription, $this> */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function encaissePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    /** @return BelongsTo<User, $this> */
    public function annulePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function estAnnule(): bool
    {
        return $this->annule_le !== null;
    }

    #[Scope]
    protected function valides(Builder $query): void
    {
        $query->whereNull('annule_le');
    }
}
