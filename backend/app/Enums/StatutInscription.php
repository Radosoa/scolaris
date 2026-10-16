<?php

namespace App\Enums;

enum StatutInscription: string
{
    case Inscrit = 'inscrit';
    case Transfere = 'transfere';
    case Abandon = 'abandon';

    public function libelle(): string
    {
        return match ($this) {
            self::Inscrit => 'Inscrit',
            self::Transfere => 'Transféré',
            self::Abandon => 'Abandon',
        };
    }
}
