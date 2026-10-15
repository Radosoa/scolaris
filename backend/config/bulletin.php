<?php

/*
|--------------------------------------------------------------------------
| Règles de calcul des bulletins
|--------------------------------------------------------------------------
|
| Utilisées par BulletinService (phase 5). Les valeurs peuvent être
| surchargées via le fichier .env sans toucher au code.
|
*/

return [

    // Pondération de la moyenne par matière (doit totaliser 1).
    // Si une catégorie n'a aucune note, seule l'autre est prise en compte.
    'ponderations' => [
        'controle_continu' => (float) env('BULLETIN_POIDS_CONTROLE_CONTINU', 0.4), // devoirs + interrogations
        'composition' => (float) env('BULLETIN_POIDS_COMPOSITION', 0.6),
    ],

    // Toutes les notes sont ramenées sur cette échelle avant calcul
    'echelle' => 20,

    // Seuils des mentions, du plus élevé au plus bas (moyenne générale >= seuil)
    'mentions' => [
        ['seuil' => 16, 'libelle' => 'Félicitations'],
        ['seuil' => 14, 'libelle' => 'Tableau d\'honneur avec encouragements'],
        ['seuil' => 12, 'libelle' => 'Tableau d\'honneur'],
        ['seuil' => 10, 'libelle' => 'Passable'],
        ['seuil' => 0, 'libelle' => 'Avertissement travail'],
    ],

    // Nombre de décimales affichées (les rangs utilisent les valeurs exactes)
    'decimales_affichage' => 2,

];
