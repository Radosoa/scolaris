<?php

namespace App\Services;

use App\Models\Parametre;
use App\Support\Devise;
use App\Support\EcoleCourante;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Réglages de l'établissement, en cache jusqu'à leur prochaine modification. */
class ParametresService
{
    private const CACHE = 'parametres';

    /** Champs de l'identité de l'école (en-tête des bulletins et reçus). */
    public const IDENTITE = ['nom', 'adresse', 'ville', 'telephone', 'email', 'chef_etablissement'];

    public function __construct(private readonly EcoleCourante $ecoles) {}

    /** Devise choisie, sinon celle de la configuration (ECOLE_DEVISE), sinon l'euro. */
    public function devise(): Devise
    {
        return Devise::depuisCode((string) ($this->tous()['devise'] ?? ''))
            ?? Devise::depuisCode((string) config('ecole.devise'))
            ?? Devise::depuisCode('EUR');
    }

    public function definirDevise(string $code): Devise
    {
        $this->enregistrer(['devise' => $code]);

        return $this->devise();
    }

    /**
     * Identité de l'école : valeurs saisies par la direction, sinon celles de config/ecole.php
     * (variables ECOLE_* du .env, ou le nom donné à la création de l'école).
     *
     * @return array<string, string|null>
     */
    public function identite(): array
    {
        $parametres = $this->tous();
        $defauts = config('ecole');
        $ecole = $this->ecoles->get();

        $identite = [];
        foreach (self::IDENTITE as $champ) {
            $identite[$champ] = $parametres["ecole.{$champ}"]
                ?? ($ecole ? ($champ === 'nom' ? $ecole->nom : null) : ($defauts[$champ] ?? null));
        }

        return $identite;
    }

    /**
     * @param  array<string, string|null>  $identite
     * @return array<string, string|null>
     */
    public function definirIdentite(array $identite): array
    {
        DB::transaction(function () use ($identite) {
            $this->enregistrer(collect(self::IDENTITE)->mapWithKeys(fn ($champ) => ["ecole.{$champ}" => $identite[$champ] ?? null])->all());
            // Nom recopié dans la base centrale (liste des écoles, écran de connexion)
            $this->ecoles->get()?->update(['nom' => $identite['nom']]);
        });

        return $this->identite();
    }

    /** @param  array<string, string|null>  $valeurs */
    private function enregistrer(array $valeurs): void
    {
        foreach ($valeurs as $cle => $valeur) {
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
        }
        Cache::forget(self::CACHE);
    }

    /** @return array<string, string|null> */
    private function tous(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => Parametre::pluck('valeur', 'cle')->all());
    }
}
