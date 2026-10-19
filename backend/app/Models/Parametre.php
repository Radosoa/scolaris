<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Réglage de l'établissement (ex. devise). Lu via ParametresService, qui le met en cache. */
#[Fillable(['cle', 'valeur'])]
class Parametre extends Model
{
    protected $primaryKey = 'cle';

    protected $keyType = 'string';

    public $incrementing = false;
}
