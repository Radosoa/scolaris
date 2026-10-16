<?php

namespace Database\Seeders;

use App\Enums\StatutInscription;
use App\Models\Absence;
use App\Models\AnneeScolaire;
use App\Models\Creneau;
use App\Models\Eleve;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Appels de démonstration de la rentrée jusqu'à hier : chaque cours a son appel, avec
 * ~1,5 % d'élèves absents à la journée (quelques élèves bien plus souvent) et des retards.
 * Les absences de plus d'une semaine sont en général justifiées ; les plus récentes
 * attendent la vie scolaire. Aujourd'hui reste à faire (démo de l'appel).
 * Ne fait rien si des appels existent déjà.
 */
class AbsencesSeeder extends Seeder
{
    private const MOTIFS = ['Maladie', 'Rendez-vous médical', 'Raison familiale', 'Certificat médical', 'Convocation administrative'];

    public function run(): void
    {
        if (DB::table('appels')->exists()) {
            $this->command?->info('Des appels existent déjà : AbsencesSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->firstOrFail();
        $fin = CarbonImmutable::yesterday()->min($annee->date_fin);
        if ($fin->lt($annee->date_debut)) {
            return;
        }

        mt_srand(1515);
        $creneaux = Creneau::with('affectation.enseignant')->get()->groupBy('jour');
        $classes = DB::table('inscriptions')
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit->value)
            ->get(['eleve_id', 'classe_id'])
            ->groupBy('classe_id')
            ->map(fn ($lignes) => $lignes->pluck('eleve_id')->all());

        // Assiduité propre à chaque élève : la plupart très assidus, quelques absentéistes
        $tauxAbsence = [];
        foreach ($classes->flatten() as $eleveId) {
            $tauxAbsence[$eleveId] = mt_rand(1, 100) <= 4 ? 0.08 : 0.012;
        }
        $demo = Eleve::whereHas('user', fn ($q) => $q->where('email', 'eleve@scolaris.test'))->value('id');

        $appels = [];
        $absences = [];
        $maintenant = now();
        $semaineDerniere = CarbonImmutable::today()->subWeek();

        foreach (CarbonPeriod::create($annee->date_debut, $fin) as $jour) {
            $date = CarbonImmutable::instance($jour);
            $coursDuJour = $creneaux->get($date->dayOfWeekIso, collect());
            if ($coursDuJour->isEmpty()) {
                continue;
            }

            // Absents de la journée et leur éventuel motif (le même pour toute la journée)
            $absentsDuJour = [];
            foreach ($tauxAbsence as $eleveId => $taux) {
                if ($this->tirage($taux) || ($eleveId === $demo && $date->toDateString() === $this->jourDemo($annee))) {
                    $justifiee = $date->lt($semaineDerniere) ? $this->tirage(0.8) : $this->tirage(0.2);
                    $absentsDuJour[$eleveId] = $justifiee ? self::MOTIFS[mt_rand(0, count(self::MOTIFS) - 1)] : false;
                }
            }

            foreach ($coursDuJour as $creneau) {
                $appels[] = [
                    'creneau_id' => $creneau->id,
                    'date' => $date->toDateString(),
                    'fait_par' => $creneau->affectation->enseignant->user_id,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];

                foreach ($classes->get($creneau->affectation->classe_id, []) as $eleveId) {
                    $ligne = null;
                    if (array_key_exists($eleveId, $absentsDuJour)) {
                        $motif = $absentsDuJour[$eleveId];
                        $ligne = ['type' => 'absence', 'heure_fin' => $creneau->heure_fin, 'justifiee' => $motif !== false, 'motif' => $motif ?: null];
                    } elseif ($this->tirage(0.006)) {
                        $justifie = $this->tirage(0.3);
                        $ligne = [
                            'type' => 'retard',
                            'heure_fin' => sprintf('%s:%02d', substr($creneau->heure_debut, 0, 2), mt_rand(1, 4) * 5),
                            'justifiee' => $justifie,
                            'motif' => $justifie ? 'Retard de transport' : null,
                        ];
                    }

                    if ($ligne) {
                        $absences[] = [
                            'eleve_id' => $eleveId,
                            'creneau_id' => $creneau->id,
                            'date' => $date->toDateString(),
                            'heure_debut' => $creneau->heure_debut,
                            'saisie_par' => $creneau->affectation->enseignant->user_id,
                            'created_at' => $maintenant,
                            'updated_at' => $maintenant,
                            ...$ligne,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($appels, 500) as $lot) {
            DB::table('appels')->insert($lot);
        }
        foreach (array_chunk($absences, 500) as $lot) {
            Absence::insert($lot);
        }
    }

    private function tirage(float $probabilite): bool
    {
        return mt_rand() / mt_getrandmax() < $probabilite;
    }

    /** Une journée d'absence pour l'élève de démonstration : le deuxième lundi de l'année. */
    private function jourDemo(AnneeScolaire $annee): string
    {
        return CarbonImmutable::parse($annee->date_debut)->next('Monday')->addWeek()->toDateString();
    }
}
