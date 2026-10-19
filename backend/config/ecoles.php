<?php

/*
|--------------------------------------------------------------------------
| Multi-établissement (une base de données par école)
|--------------------------------------------------------------------------
|
| La base centrale (connexion « central ») liste les écoles ; chaque école a sa
| propre base, numérotée dans l'ordre de création : scolaris500001, scolaris500002…
| Le front désigne l'école par l'en-tête X-Ecole (code saisi à la connexion, ou sous-domaine).
|
| multi = false : une seule école, la base DB_DATABASE (installation simple, tests).
|
*/

return [

    'multi' => (bool) env('ECOLES_MULTI', false),

    // Bases numérotées dans l'ordre de création : scolaris500001, scolaris500002…
    'prefixe_base' => env('ECOLES_PREFIXE_BASE', 'scolaris'),

    'premier_numero' => (int) env('ECOLES_PREMIER_NUMERO', 500001),

    'entete' => 'X-Ecole',

];
