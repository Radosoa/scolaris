<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lecture brute du fichier d'import : la première ligne donne les en-têtes
 * (convertis en slug : « Date de naissance » → date_de_naissance).
 * La validation et l'enregistrement sont faits par ImportElevesService.
 */
class LectureEleves implements Import, WithCalculatedFormulas, WithHeadingRow {}
