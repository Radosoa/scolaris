<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use Illuminate\Validation\ValidationException;

class ClasseService
{
    /**
     * @param  array{niveau_id?: int, annee_scolaire_id?: int, nom?: string, capacite?: int}  $donnees
     */
    public function modifier(Classe $classe, array $donnees): Classe
    {
        $classe->fill($donnees);
        $effectif = $classe->effectif();

        if ($classe->isDirty('annee_scolaire_id') && $effectif > 0) {
            throw new RegleMetierException('Impossible de changer l\'année d\'une classe qui a des élèves inscrits.');
        }

        if ($classe->capacite < $effectif) {
            throw ValidationException::withMessages([
                'capacite' => "La capacité ne peut pas être inférieure à l'effectif actuel ({$effectif} élèves).",
            ]);
        }

        $classe->save();

        return $classe;
    }
}
