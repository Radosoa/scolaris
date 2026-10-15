<?php

namespace App\Enums;

/**
 * Permissions fonctionnelles. Le périmètre fin (« ses classes », « ses enfants »)
 * est contrôlé par les Policies, pas par les permissions.
 */
enum Permission: string
{
    case StructureGerer = 'structure.gerer';           // années, périodes, niveaux, classes, matières, coefficients
    case EnseignantsGerer = 'enseignants.gerer';
    case AffectationsGerer = 'affectations.gerer';
    case ElevesGerer = 'eleves.gerer';
    case TuteursGerer = 'tuteurs.gerer';
    case InscriptionsGerer = 'inscriptions.gerer';
    case NotesSaisir = 'notes.saisir';
    case AppreciationsSaisir = 'appreciations.saisir';
    case AbsencesSaisir = 'absences.saisir';
    case AbsencesJustifier = 'absences.justifier';
    case EmploiDuTempsGerer = 'emploi-du-temps.gerer';
    case BulletinsGenerer = 'bulletins.generer';
    case BulletinsValider = 'bulletins.valider';
    case BulletinsConsulter = 'bulletins.consulter';
    case PaiementsGerer = 'paiements.gerer';
    case AnnoncesPublier = 'annonces.publier';
    case DocumentsEditer = 'documents.editer';
    case StatistiquesVoir = 'statistiques.voir';
    case UtilisateursGerer = 'utilisateurs.gerer';
}
