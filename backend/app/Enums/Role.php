<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Secretariat = 'secretariat';
    case Professeur = 'professeur';
    case Eleve = 'eleve';
    case Parent = 'parent';

    public function libelle(): string
    {
        return match ($this) {
            self::Admin => 'Direction',
            self::Secretariat => 'Secrétariat',
            self::Professeur => 'Professeur',
            self::Eleve => 'Élève',
            self::Parent => 'Parent',
        };
    }

    /**
     * Permissions accordées à chaque rôle.
     * L'admin reçoit toutes les permissions (voir RolePermissionSeeder).
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Secretariat => [
                Permission::ElevesGerer,
                Permission::TuteursGerer,
                Permission::InscriptionsGerer,
                Permission::AbsencesJustifier,
                Permission::PaiementsGerer,
                Permission::AnnoncesPublier,
                Permission::DocumentsEditer,
            ],
            self::Professeur => [
                Permission::NotesSaisir,
                Permission::AbsencesSaisir,
                Permission::AppreciationsSaisir,
            ],
            self::Eleve, self::Parent => [
                Permission::BulletinsConsulter,
            ],
        };
    }
}
