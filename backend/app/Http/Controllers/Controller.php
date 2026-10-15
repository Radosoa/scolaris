<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Taille de page demandée (?per_page=), bornée entre 1 et 100. */
    protected function parPage(Request $request, int $defaut = 15): int
    {
        return max(1, min(100, $request->integer('per_page', $defaut)));
    }
}
