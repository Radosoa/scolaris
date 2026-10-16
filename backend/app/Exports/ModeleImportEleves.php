<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Fichier modèle téléchargeable pour l'import d'élèves. */
class ModeleImportEleves implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'classe'];
    }

    public function array(): array
    {
        return [
            ['Dupont', 'Camille', 'F', '14/03/2014', 'Lyon', '6e A'],
            ['Martin', 'Hugo', 'M', '02/11/2013', 'Paris', ''],
        ];
    }
}
