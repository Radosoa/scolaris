<?php

namespace App\Models;

use App\Enums\TypeAbsence;
use App\Policies\AbsencePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absence ou retard d'un élève sur un créneau, saisi lors de l'appel.
 * Pour un retard, heure_fin = heure d'arrivée de l'élève.
 */
#[Table('absences')]
#[Fillable(['eleve_id', 'creneau_id', 'date', 'heure_debut', 'heure_fin', 'type', 'justifiee', 'motif'])]
#[UsePolicy(AbsencePolicy::class)]
class Absence extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['justifiee' => false];

    protected function casts(): array
    {
        return [
            'type' => TypeAbsence::class,
            'date' => 'date',
            'justifiee' => 'boolean',
        ];
    }

    protected function heureDebut(): Attribute
    {
        return Attribute::get(fn (?string $valeur) => $valeur === null ? null : substr($valeur, 0, 5));
    }

    protected function heureFin(): Attribute
    {
        return Attribute::get(fn (?string $valeur) => $valeur === null ? null : substr($valeur, 0, 5));
    }

    /** @return BelongsTo<Eleve, $this> */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class)->withTrashed();
    }

    /** @return BelongsTo<Creneau, $this> */
    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }

    /** @return BelongsTo<User, $this> */
    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    /** Durée en heures (ex. 08:00 → 09:30 = 1,5) ; pour un retard, la durée du retard. */
    public function dureeEnHeures(): float
    {
        return self::minutesEntre($this->heure_debut, $this->heure_fin) / 60;
    }

    /** Minutes entre deux heures « HH:MM » (0 si la fin précède le début). */
    public static function minutesEntre(string $debut, string $fin): int
    {
        [$hd, $md] = array_map('intval', explode(':', $debut));
        [$hf, $mf] = array_map('intval', explode(':', $fin));

        return max(0, ($hf * 60 + $mf) - ($hd * 60 + $md));
    }
}
