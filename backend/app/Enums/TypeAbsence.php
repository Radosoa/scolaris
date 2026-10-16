<?php

namespace App\Enums;

enum TypeAbsence: string
{
    case Absence = 'absence';
    case Retard = 'retard';

    public function libelle(): string
    {
        return match ($this) {
            self::Absence => 'Absence',
            self::Retard => 'Retard',
        };
    }
}
