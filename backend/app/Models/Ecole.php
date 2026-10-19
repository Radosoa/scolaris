<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * École cliente (base centrale). Ses données vivent dans sa propre base (base_de_donnees) ;
 * le nom est recopié ici pour la liste des écoles et l'écran de connexion.
 */
#[Fillable(['code', 'base_de_donnees', 'nom', 'actif'])]
class Ecole extends Model
{
    protected $connection = 'central';

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
