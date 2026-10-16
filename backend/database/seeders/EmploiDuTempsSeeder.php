<?php

namespace Database\Seeders;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Creneau;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Emploi du temps de démonstration de l'année active, sans aucun conflit (professeur,
 * classe, salle) : placement glouton des séances avec une nouvelle tentative si une
 * séance ne trouve pas de place. Ne fait rien si des créneaux existent déjà.
 */
class EmploiDuTempsSeeder extends Seeder
{
    /** Séances hebdomadaires par matière (durées en heures). */
    private const SEANCES = [
        'FR' => [2, 1],
        'MATH' => [1, 1, 1],
        'HG' => [1, 1, 1],
        'ANG' => [1, 1, 1],
        'ESP' => [1, 1],
        'SVT' => [2],
        'PC' => [2],
        'TECH' => [2],
        'EPS' => [2, 1],
        'ART' => [1],
    ];

    /** Salles spécialisées ; les autres cours ont lieu dans la salle de la classe. */
    private const SALLES = [
        'SVT' => 'Labo SVT',
        'PC' => 'Labo physique',
        'TECH' => 'Salle de technologie',
        'EPS' => 'Gymnase',
        'ART' => 'Salle d\'arts plastiques',
    ];

    /** Heures de début possibles : matin 8 h – 12 h, après-midi 14 h – 17 h, mercredi matin seulement. */
    private const DEMI_JOURNEES = [[8, 9, 10, 11], [14, 15, 16]];

    public function run(): void
    {
        if (Creneau::exists()) {
            $this->command?->info('Des créneaux existent déjà : EmploiDuTempsSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->firstOrFail();
        $affectations = Affectation::with(['classe.niveau', 'matiere'])
            ->whereHas('classe', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->get();

        foreach (range(1, 50) as $tentative) {
            mt_srand(1000 + $tentative);
            $placement = $this->placer($affectations);
            if ($placement !== null) {
                Creneau::insert($placement);

                return;
            }
        }

        throw new RuntimeException('Impossible de construire un emploi du temps sans conflit.');
    }

    /**
     * @param  Collection<int, Affectation>  $affectations
     * @return list<array<string, mixed>>|null null si une séance n'a pas trouvé de place
     */
    private function placer(Collection $affectations): ?array
    {
        $occupe = [];   // "classe:12|jour|heure", "prof:3|jour|heure" => true
        $parJour = [];  // "classe|matiere|jour" => true : pas deux fois la même matière dans la journée
        $lignes = [];

        // Les séances longues et les professeurs les plus chargés d'abord
        $seances = $affectations->flatMap(fn (Affectation $a) => array_map(
            fn (int $duree) => ['affectation' => $a, 'duree' => $duree],
            self::SEANCES[$a->matiere->code] ?? [1],
        ))->sortBy(fn () => mt_rand())->sortByDesc('duree')->values();

        foreach ($seances as ['affectation' => $a, 'duree' => $duree]) {
            $candidats = [];
            foreach ($this->debutsPossibles($duree) as [$jour, $heure]) {
                $heures = range($heure, $heure + $duree - 1);
                $libre = collect($heures)->every(fn (int $h) => ! isset($occupe["classe:{$a->classe_id}|{$jour}|{$h}"])
                    && ! isset($occupe["prof:{$a->enseignant_id}|{$jour}|{$h}"]));
                if ($libre) {
                    $penalite = isset($parJour["{$a->classe_id}|{$a->matiere_id}|{$jour}"]) ? 10 : 0;
                    $candidats[] = [$penalite + mt_rand(0, 9), $jour, $heure];
                }
            }
            if ($candidats === []) {
                return null;
            }

            sort($candidats);
            [, $jour, $heure] = $candidats[0];
            foreach (range($heure, $heure + $duree - 1) as $h) {
                $occupe["classe:{$a->classe_id}|{$jour}|{$h}"] = true;
                $occupe["prof:{$a->enseignant_id}|{$jour}|{$h}"] = true;
            }
            $parJour["{$a->classe_id}|{$a->matiere_id}|{$jour}"] = true;

            $lignes[] = [
                'affectation_id' => $a->id,
                'jour' => $jour,
                'heure_debut' => sprintf('%02d:00', $heure),
                'heure_fin' => sprintf('%02d:00', $heure + $duree),
                // Une salle spécialisée n'est utilisée que par un professeur : pas de conflit de salle possible
                'salle' => self::SALLES[$a->matiere->code] ?? $this->salleDeClasse($a),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $lignes;
    }

    /** @return list<array{0: int, 1: int}> [jour, heure de début] d'une séance de cette durée */
    private function debutsPossibles(int $duree): array
    {
        $debuts = [];
        foreach (range(1, 5) as $jour) {
            $demiJournees = $jour === 3 ? [self::DEMI_JOURNEES[0]] : self::DEMI_JOURNEES;
            foreach ($demiJournees as $heures) {
                foreach ($heures as $heure) {
                    if (in_array($heure + $duree - 1, $heures, true)) {
                        $debuts[] = [$jour, $heure];
                    }
                }
            }
        }

        return $debuts;
    }

    /** 6e A → salle 101, 6e B → salle 102, 5e A → salle 201… */
    private function salleDeClasse(Affectation $a): string
    {
        $lettre = substr($a->classe->nom, -1);

        return sprintf('Salle %d%02d', $a->classe->niveau->ordre, ord($lettre) - ord('A') + 1);
    }
}
