<?php

namespace App\Services\Finances;

/**
 * Montant écrit en toutes lettres pour les reçus, selon l'orthographe traditionnelle :
 * traits d'union sous cent, « et » devant un et onze (vingt et un, soixante et onze),
 * « vingt » et « cent » au pluriel seulement en fin de nombre (quatre-vingts, deux cents,
 * mais quatre-vingt-un, deux cent mille), « mille » invariable.
 */
final class MontantEnLettres
{
    private const UNITES = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
    ];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    /** Ex. 1250.5 → « mille deux cent cinquante euros et cinquante centimes ». */
    public static function euros(float|int|string $montant): string
    {
        $centimesTotal = (int) round((float) $montant * 100);
        $euros = intdiv($centimesTotal, 100);
        $centimes = $centimesTotal % 100;

        $parties = [];
        if ($euros > 0 || $centimes === 0) {
            $unite = match (true) {
                $euros <= 1 => 'euro',
                // « un million d'euros », mais « un million deux cents euros »
                $euros % 1_000_000 === 0 => 'd\'euros',
                default => 'euros',
            };
            $parties[] = self::nombre($euros).($unite === 'd\'euros' ? ' d\'euros' : ' '.$unite);
        }
        if ($centimes > 0) {
            $parties[] = self::nombre($centimes).' centime'.($centimes > 1 ? 's' : '');
        }

        return implode(' et ', $parties);
    }

    /** Entier positif en lettres (jusqu'à 999 999 999). */
    public static function nombre(int $n): string
    {
        if ($n === 0) {
            return self::UNITES[0];
        }

        $millions = intdiv($n, 1_000_000);
        $milliers = intdiv($n % 1_000_000, 1000);
        $reste = $n % 1000;

        $mots = [];
        if ($millions > 0) {
            $mots[] = $millions === 1 ? 'un million' : self::moinsDeMille($millions, true).' millions';
        }
        if ($milliers > 0) {
            // « mille » est invariable et ne prend pas « un » ; vingt et cent restent au singulier devant lui
            $mots[] = $milliers === 1 ? 'mille' : self::moinsDeMille($milliers, false).' mille';
        }
        if ($reste > 0) {
            $mots[] = self::moinsDeMille($reste, true);
        }

        return implode(' ', $mots);
    }

    /** @param  bool  $final  le nombre termine-t-il le montant (accord de « cents » et « quatre-vingts ») */
    private static function moinsDeMille(int $n, bool $final): string
    {
        $centaines = intdiv($n, 100);
        $reste = $n % 100;

        $mots = [];
        if ($centaines === 1) {
            $mots[] = 'cent';
        } elseif ($centaines > 1) {
            $mots[] = self::UNITES[$centaines].' cent'.($final && $reste === 0 ? 's' : '');
        }
        if ($reste > 0) {
            $mots[] = self::moinsDeCent($reste, $final);
        }

        return implode(' ', $mots);
    }

    private static function moinsDeCent(int $n, bool $final): string
    {
        if ($n <= 16) {
            return self::UNITES[$n];
        }
        if ($n < 20) {
            return 'dix-'.self::UNITES[$n - 10];
        }

        $dizaine = intdiv($n, 10);
        $unite = $n % 10;

        // 70-79 et 90-99 se construisent sur soixante + 10..19 et quatre-vingt + 10..19
        if ($dizaine === 7 || $dizaine === 9) {
            $base = $dizaine === 7 ? 'soixante' : 'quatre-vingt';
            $suite = $n - ($dizaine === 7 ? 60 : 80);

            return $base.($n === 71 ? ' et ' : '-').self::moinsDeCent($suite, false);
        }
        if ($dizaine === 8) {
            return $unite === 0 ? 'quatre-vingt'.($final ? 's' : '') : 'quatre-vingt-'.self::UNITES[$unite];
        }

        $base = self::DIZAINES[$dizaine];

        return match ($unite) {
            0 => $base,
            1 => "{$base} et un",
            default => "{$base}-".self::UNITES[$unite],
        };
    }
}
