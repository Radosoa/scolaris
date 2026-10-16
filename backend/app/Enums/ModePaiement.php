<?php

namespace App\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case Cheque = 'cheque';
    case Virement = 'virement';
    case Carte = 'carte';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Cheque => 'Chèque',
            self::Virement => 'Virement',
            self::Carte => 'Carte bancaire',
        };
    }

    /** Une référence (n° de chèque, de virement, de transaction) est exigée hors espèces. */
    public function exigeReference(): bool
    {
        return $this !== self::Especes;
    }
}
