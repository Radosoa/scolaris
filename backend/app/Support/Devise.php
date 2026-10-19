<?php

namespace App\Support;

/**
 * Devise dans laquelle l'établissement exprime ses frais et ses paiements.
 * Changer de devise ne convertit pas les montants : seul leur affichage change.
 */
final readonly class Devise
{
    /**
     * Catalogue des devises proposées : libellé, symbole, nombre de décimales,
     * puis noms de l'unité et de la sous-unité (singulier, pluriel) pour les montants en lettres.
     */
    private const CATALOGUE = [
        'MGA' => ['Ariary malgache', 'Ar', 0, ['ariary', 'ariary'], null],
        'EUR' => ['Euro', '€', 2, ['euro', 'euros'], ['centime', 'centimes']],
        'USD' => ['Dollar américain', '$', 2, ['dollar', 'dollars'], ['cent', 'cents']],
        'XOF' => ['Franc CFA (UEMOA)', 'F CFA', 0, ['franc CFA', 'francs CFA'], null],
        'XAF' => ['Franc CFA (CEMAC)', 'FCFA', 0, ['franc CFA', 'francs CFA'], null],
        'KMF' => ['Franc comorien', 'FC', 0, ['franc comorien', 'francs comoriens'], null],
        'MUR' => ['Roupie mauricienne', 'Rs', 2, ['roupie', 'roupies'], ['cent', 'cents']],
        'MAD' => ['Dirham marocain', 'DH', 2, ['dirham', 'dirhams'], ['centime', 'centimes']],
        'CAD' => ['Dollar canadien', '$ CA', 2, ['dollar canadien', 'dollars canadiens'], ['cent', 'cents']],
        'CHF' => ['Franc suisse', 'CHF', 2, ['franc suisse', 'francs suisses'], ['centime', 'centimes']],
        'GBP' => ['Livre sterling', '£', 2, ['livre sterling', 'livres sterling'], ['penny', 'pence']],
    ];

    /**
     * @param  array{string, string}  $unite  singulier, pluriel
     * @param  array{string, string}|null  $sousUnite  null si la devise n'a pas de décimales
     */
    private function __construct(
        public string $code,
        public string $libelle,
        public string $symbole,
        public int $decimales,
        public array $unite,
        public ?array $sousUnite,
    ) {}

    public static function depuisCode(string $code): ?self
    {
        $d = self::CATALOGUE[$code] ?? null;

        return $d ? new self($code, ...$d) : null;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /** @return list<self> */
    public static function toutes(): array
    {
        return array_map(fn (string $code) => self::depuisCode($code), self::codes());
    }

    /** 125050 centimes → « 1 250,50 € » ; en ariary, sans décimales → « 1 251 Ar ». */
    public function formater(int $centimes): string
    {
        return number_format($centimes / 100, $this->decimales, ',', ' ').' '.$this->symbole;
    }

    /** @return array{code: string, libelle: string, symbole: string, decimales: int} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'libelle' => $this->libelle, 'symbole' => $this->symbole, 'decimales' => $this->decimales];
    }
}
