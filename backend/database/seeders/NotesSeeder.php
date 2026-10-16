<?php

namespace Database\Seeders;

use App\Enums\StatutInscription;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Evaluation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Notes de démonstration du premier trimestre : trois évaluations par affectation
 * (deux devoirs et une interrogation), notes cohérentes par élève (un « niveau »
 * propre à chaque élève + variation par matière) et ~3 % d'absences.
 * Ne fait rien si des évaluations existent déjà.
 */
class NotesSeeder extends Seeder
{
    public function run(): void
    {
        if (Evaluation::exists()) {
            $this->command?->info('Des évaluations existent déjà : NotesSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->with('periodes')->firstOrFail();
        $trimestre = $annee->periodes->firstWhere('ordre', 1);
        if (! $trimestre) {
            return;
        }

        mt_srand(2026);
        $niveaux = [];   // niveau moyen de chaque élève, sur 20
        $modeles = [
            ['type' => 'devoir', 'titre' => 'Devoir 1', 'jours' => 14, 'bareme' => 20],
            ['type' => 'interrogation', 'titre' => 'Interrogation écrite', 'jours' => 24, 'bareme' => 10],
            ['type' => 'devoir', 'titre' => 'Devoir 2', 'jours' => 35, 'bareme' => 20],
        ];

        $affectations = Affectation::with(['classe.inscriptions' => fn ($q) => $q->where('statut', StatutInscription::Inscrit)])
            ->whereHas('classe', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->get();

        foreach ($affectations as $affectation) {
            $ecartMatiere = [];
            foreach ($modeles as $modele) {
                $evaluation = Evaluation::create([
                    'affectation_id' => $affectation->id,
                    'periode_id' => $trimestre->id,
                    'type' => $modele['type'],
                    'titre' => $modele['titre'],
                    'date' => $trimestre->date_debut->copy()->addDays($modele['jours'])->toDateString(),
                    'bareme' => $modele['bareme'],
                ]);

                $lignes = [];
                foreach ($affectation->classe->inscriptions as $inscription) {
                    $id = $inscription->eleve_id;
                    $niveaux[$id] ??= $this->gauss(12, 3);
                    $ecartMatiere[$id] ??= $this->gauss(0, 1.8);
                    $absent = mt_rand(1, 100) <= 3;
                    $sur20 = max(0, min(20, $niveaux[$id] + $ecartMatiere[$id] + $this->gauss(0, 2)));

                    $lignes[] = [
                        'evaluation_id' => $evaluation->id,
                        'eleve_id' => $id,
                        'valeur' => $absent ? null : round($sur20 * $modele['bareme'] / 20 * 4) / 4, // au quart de point
                        'absent' => $absent,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('notes')->insert($lignes);
            }
        }
    }

    /** Tirage selon une loi normale (Box-Muller). */
    private function gauss(float $moyenne, float $ecartType): float
    {
        $u = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $v = mt_rand(1, mt_getrandmax()) / mt_getrandmax();

        return $moyenne + $ecartType * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
