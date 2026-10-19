<?php

/*
|--------------------------------------------------------------------------
| Identité de l'établissement
|--------------------------------------------------------------------------
|
| Affichée en en-tête des documents PDF (bulletins, reçus).
|
*/

return [

    'nom' => env('ECOLE_NOM', 'Collège Jean Moulin'),
    'adresse' => env('ECOLE_ADRESSE', '12 rue des Écoles, 69003 Lyon'),
    'telephone' => env('ECOLE_TELEPHONE', '04 78 00 00 00'),
    'email' => env('ECOLE_EMAIL', 'secretariat@college-jean-moulin.test'),
    'ville' => env('ECOLE_VILLE', 'Lyon'),
    'chef_etablissement' => env('ECOLE_CHEF_ETABLISSEMENT', 'Le Principal'),

    // Devise tant que la direction n'en a pas choisi une dans les paramètres (code ISO : MGA, EUR, USD…)
    'devise' => env('ECOLE_DEVISE', 'EUR'),

];
