<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\User;
use App\Services\Bulletins\BulletinService;
use App\Services\PeriodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bulletins de démonstration du trimestre 1, clôturé pour l'occasion, avec les trois états
 * de l'écran de la direction :
 * - classes « A » : calculés, appréciations saisies, validés (consultables par élèves et parents) ;
 * - autres classes « B » : calculés mais pas encore validés (le professeur peut saisir ses appréciations) ;
 * - 5e B : non calculés (pour essayer « Calculer »).
 * Ne fait rien si des bulletins existent déjà.
 */
class BulletinsSeeder extends Seeder
{
    /** Appréciations par tranche de moyenne (sur 20). */
    private const APPRECIATIONS = [
        16 => ['Excellent trimestre, bravo.', 'Très bons résultats, continuez ainsi.', 'Travail remarquable et régulier.'],
        14 => ['Très bon trimestre.', 'Bon travail, sérieux et investi.', 'Très bons résultats, poursuivez vos efforts.'],
        12 => ['Bon trimestre dans l\'ensemble.', 'Résultats satisfaisants, travail régulier.', 'Bon ensemble, peut viser plus haut.'],
        10 => ['Résultats justes, des efforts à fournir.', 'Ensemble moyen, il faut travailler plus régulièrement.', 'Peut mieux faire avec davantage de rigueur.'],
        8 => ['Résultats fragiles, il faut s\'investir davantage.', 'Trimestre difficile, des lacunes à combler.', 'Manque de travail personnel.'],
        0 => ['Trimestre très insuffisant, un travail sérieux s\'impose.', 'Résultats très faibles : il faut se ressaisir.', 'Grandes difficultés, un accompagnement est nécessaire.'],
    ];

    private const GENERALES = [
        16 => 'Excellent trimestre. Félicitations du conseil de classe.',
        14 => 'Très bon trimestre, élève sérieux et impliqué. Encouragements.',
        12 => 'Bon trimestre. Le travail est régulier, il faut persévérer.',
        10 => 'Trimestre correct mais irrégulier. Des efforts sont attendus.',
        0 => 'Trimestre insuffisant. Un travail plus soutenu est indispensable.',
    ];

    public function run(BulletinService $bulletins, PeriodeService $periodes): void
    {
        if (Bulletin::exists()) {
            $this->command?->info('Des bulletins existent déjà : BulletinsSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->with('periodes')->firstOrFail();
        $trimestre = $annee->periodes->firstWhere('ordre', 1);
        $directeur = User::where('email', 'admin@scolaris.test')->firstOrFail();
        $periodes->cloturer($trimestre);

        mt_srand(1789);
        $classes = Classe::where('annee_scolaire_id', $annee->id)->orderBy('nom')->get();

        foreach ($classes as $classe) {
            $aValider = str_ends_with($classe->nom, 'A');
            if ($classe->nom === '5e B') {
                continue;
            }

            $calcules = $bulletins->calculer($classe, $trimestre);
            if (! $aValider) {
                continue;
            }

            DB::transaction(function () use ($calcules) {
                foreach ($calcules as $bulletin) {
                    $bulletin->forceFill(['appreciation_generale' => $this->choisir(self::GENERALES, $bulletin->moyenne)])->save();
                    foreach ($bulletin->lignes as $ligne) {
                        $ligne->forceFill(['appreciation' => $this->choisir(self::APPRECIATIONS, $ligne->moyenne)])->save();
                    }
                }
            });
            $bulletins->valider($classe, $trimestre, $directeur);
        }
    }

    /**
     * Texte de la plus haute tranche atteinte (un au hasard si plusieurs variantes).
     *
     * @param  array<int, string|list<string>>  $tranches
     */
    private function choisir(array $tranches, ?float $moyenne): ?string
    {
        if ($moyenne === null) {
            return null;
        }

        foreach ($tranches as $seuil => $textes) {
            if ($moyenne >= $seuil) {
                return is_array($textes) ? $textes[mt_rand(0, count($textes) - 1)] : $textes;
            }
        }

        return null;
    }
}
