<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Appel effectué sur un créneau à une date donnée. */
#[Table('appels')]
#[Fillable(['creneau_id', 'date', 'fait_par'])]
class Appel extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** @return BelongsTo<Creneau, $this> */
    public function creneau(): BelongsTo
    {
        return $this->belongsTo(Creneau::class);
    }

    /** @return BelongsTo<User, $this> */
    public function faitPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fait_par');
    }
}
