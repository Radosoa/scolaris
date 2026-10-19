<?php

namespace App\Http\Middleware;

use App\Models\Ecole;
use App\Support\EcoleCourante;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multi-établissement : active l'école désignée par l'en-tête X-Ecole avant toute
 * authentification (les comptes et jetons vivent dans la base de l'école).
 * Sans multi-établissement (ECOLES_MULTI=false), la base DB_DATABASE est utilisée telle quelle.
 */
class IdentifierEcole
{
    public function __construct(private readonly EcoleCourante $courante) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ecoles.multi')) {
            return $next($request);
        }

        // Les images (<img src>) ne peuvent pas envoyer d'en-tête : l'URL signée de la photo porte ?ecole=
        $code = mb_strtolower(trim((string) ($request->header(config('ecoles.entete')) ?? $request->query('ecole'))));
        $ecole = $code !== '' ? Ecole::where('code', $code)->first() : null;

        if (! $ecole) {
            $message = $code === '' ? 'Indiquez le code de votre établissement.' : 'Aucun établissement ne correspond à ce code.';

            return response()->json(['message' => $message, 'errors' => ['ecole' => [$message]]], 422);
        }
        if (! $ecole->actif) {
            return response()->json(['message' => 'L’accès à cet établissement est suspendu. Contactez l’éditeur de Scolaris.'], 403);
        }

        $this->courante->activer($ecole);

        return $next($request);
    }
}
