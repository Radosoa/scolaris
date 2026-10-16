<?php

namespace App\Models;

use App\Enums\LienParente;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Lien élève ↔ tuteur, qualifié par le lien de parenté. */
class EleveTuteur extends Pivot
{
    protected $table = 'eleve_tuteur';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['lien' => LienParente::class];
    }
}
