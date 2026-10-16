<?php

namespace App\Enums;

enum CibleAnnonce: string
{
    case Tous = 'tous';
    case Professeurs = 'professeurs';
    case Parents = 'parents';
    case Classe = 'classe';

    public function libelle(): string
    {
        return match ($this) {
            self::Tous => 'Tout l\'établissement',
            self::Professeurs => 'Professeurs',
            self::Parents => 'Parents',
            self::Classe => 'Une classe',
        };
    }
}
