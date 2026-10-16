<?php

namespace App\Enums;

enum TypeEvaluation: string
{
    case Devoir = 'devoir';
    case Interrogation = 'interrogation';
    case Composition = 'composition';

    public function libelle(): string
    {
        return match ($this) {
            self::Devoir => 'Devoir',
            self::Interrogation => 'Interrogation',
            self::Composition => 'Composition',
        };
    }

    /**
     * Catégorie de pondération du bulletin (config/bulletin.php) :
     * devoirs et interrogations forment le contrôle continu.
     */
    public function categorie(): string
    {
        return $this === self::Composition ? 'composition' : 'controle_continu';
    }
}
