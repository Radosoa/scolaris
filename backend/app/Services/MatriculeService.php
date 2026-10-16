<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\Eleve;

/**
 * Matricule unique au format AAAA-NNNN : année de début de l'année scolaire active
 * (ou année civile en cours) suivie d'un numéro séquentiel.
 * Ex. : 2026-0001, 2026-0002… La séquence dépasse 9999 sans changer de format.
 */
class MatriculeService
{
    /**
     * Doit être appelé dans une transaction : le verrou posé sur la plage de matricules
     * empêche deux créations simultanées d'obtenir le même numéro.
     */
    public function prochain(): string
    {
        $annee = AnneeScolaire::active()->value('date_debut');
        $prefixe = ($annee ? $annee->year : now()->year).'-';

        $dernier = Eleve::withTrashed()
            ->where('matricule', 'like', $prefixe.'%')
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(matricule, ?) AS UNSIGNED)) AS numero', [strlen($prefixe) + 1])
            ->value('numero');

        return $prefixe.str_pad((string) ((int) $dernier + 1), 4, '0', STR_PAD_LEFT);
    }
}
