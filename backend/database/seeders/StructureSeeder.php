<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Periode;
use App\Services\AnneeScolaireService;
use Illuminate\Database\Seeder;

/**
 * Structure d'un collège : année active en trimestres, niveaux 6e → 3e,
 * deux classes par niveau, matières et coefficients par niveau. Idempotent.
 */
class StructureSeeder extends Seeder
{
    /** Code => [nom, coefficients 6e, 5e, 4e, 3e] */
    private const MATIERES = [
        'FR' => ['Français', 4, 4, 4, 4],
        'MATH' => ['Mathématiques', 4, 4, 4, 4],
        'ANG' => ['Anglais', 2, 2, 2, 3],
        'ESP' => ['Espagnol', null, 2, 2, 2],
        'HG' => ['Histoire-Géographie', 3, 3, 3, 3],
        'SVT' => ['Sciences de la vie et de la Terre', 2, 2, 2, 2],
        'PC' => ['Physique-Chimie', null, 2, 2, 3],
        'TECH' => ['Technologie', 1, 1, 1, 1],
        'EPS' => ['Éducation physique et sportive', 1, 1, 1, 1],
        'ART' => ['Arts plastiques', 1, 1, 1, 1],
    ];

    public function run(AnneeScolaireService $annees): void
    {
        $debut = now()->month >= 8 ? now()->year : now()->year - 1;

        $annee = AnneeScolaire::updateOrCreate(
            ['libelle' => $debut.'-'.($debut + 1)],
            ['date_debut' => "{$debut}-09-01", 'date_fin' => ($debut + 1).'-07-04'],
        );
        $annees->activer($annee);

        $trimestres = [
            1 => ["{$debut}-09-01", "{$debut}-11-30"],
            2 => ["{$debut}-12-01", ($debut + 1).'-03-14'],
            3 => [($debut + 1).'-03-15', ($debut + 1).'-07-04'],
        ];
        foreach ($trimestres as $ordre => [$dateDebut, $dateFin]) {
            Periode::updateOrCreate(
                ['annee_scolaire_id' => $annee->id, 'ordre' => $ordre],
                ['libelle' => "Trimestre {$ordre}", 'date_debut' => $dateDebut, 'date_fin' => $dateFin],
            );
        }

        $niveaux = collect(['6e', '5e', '4e', '3e'])->map(fn (string $libelle, int $i) => Niveau::updateOrCreate(
            ['ordre' => $i + 1],
            ['libelle' => $libelle],
        ));

        foreach ($niveaux as $niveau) {
            foreach (['A', 'B'] as $lettre) {
                Classe::updateOrCreate(
                    ['annee_scolaire_id' => $annee->id, 'nom' => "{$niveau->libelle} {$lettre}"],
                    ['niveau_id' => $niveau->id, 'capacite' => 32],
                );
            }
        }

        // Niveaux du lycée : disponibles pour créer des classes, sans classe de démo
        foreach (['Seconde', 'Première', 'Terminale'] as $i => $libelle) {
            Niveau::updateOrCreate(['ordre' => $i + 5], ['libelle' => $libelle]);
        }

        foreach (self::MATIERES as $code => $ligne) {
            $nom = array_shift($ligne);
            $coefficients = $ligne;
            $matiere = Matiere::updateOrCreate(['code' => $code], ['nom' => $nom]);

            foreach ($niveaux as $i => $niveau) {
                if ($coefficients[$i] !== null) {
                    Coefficient::updateOrCreate(
                        ['niveau_id' => $niveau->id, 'matiere_id' => $matiere->id],
                        ['valeur' => $coefficients[$i]],
                    );
                }
            }
        }
    }
}
