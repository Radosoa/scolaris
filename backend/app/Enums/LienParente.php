<?php

namespace App\Enums;

enum LienParente: string
{
    case Pere = 'pere';
    case Mere = 'mere';
    case Tuteur = 'tuteur';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Pere => 'Père',
            self::Mere => 'Mère',
            self::Tuteur => 'Tuteur légal',
            self::Autre => 'Autre',
        };
    }
}
