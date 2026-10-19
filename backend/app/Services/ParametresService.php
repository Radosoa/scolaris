<?php

namespace App\Services;

use App\Models\Parametre;
use App\Support\Devise;
use Illuminate\Support\Facades\Cache;

/** Réglages de l'établissement, en cache jusqu'à leur prochaine modification. */
class ParametresService
{
    private const CACHE = 'parametres';

    /** Devise choisie, sinon celle de la configuration (ECOLE_DEVISE), sinon l'euro. */
    public function devise(): Devise
    {
        return Devise::depuisCode((string) ($this->tous()['devise'] ?? ''))
            ?? Devise::depuisCode((string) config('ecole.devise'))
            ?? Devise::depuisCode('EUR');
    }

    public function definirDevise(string $code): Devise
    {
        Parametre::updateOrCreate(['cle' => 'devise'], ['valeur' => $code]);
        Cache::forget(self::CACHE);

        return $this->devise();
    }

    /** @return array<string, string|null> */
    private function tous(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => Parametre::pluck('valeur', 'cle')->all());
    }
}
