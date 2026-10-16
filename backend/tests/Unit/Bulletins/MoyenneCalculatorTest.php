<?php

namespace Tests\Unit\Bulletins;

use App\Services\Bulletins\MoyenneCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoyenneCalculatorTest extends TestCase
{
    private MoyenneCalculator $calcul;

    protected function setUp(): void
    {
        $this->calcul = new MoyenneCalculator(
            ['controle_continu' => 0.4, 'composition' => 0.6],
            20,
            [
                ['seuil' => 16, 'libelle' => 'Félicitations'],
                ['seuil' => 14, 'libelle' => 'Tableau d\'honneur avec encouragements'],
                ['seuil' => 12, 'libelle' => 'Tableau d\'honneur'],
                ['seuil' => 10, 'libelle' => 'Passable'],
                ['seuil' => 0, 'libelle' => 'Avertissement travail'],
            ],
        );
    }

    /** @return array{valeur: float|null, bareme: float, categorie: string, absent: bool} */
    private function note(?float $valeur, float $bareme = 20, string $categorie = 'controle_continu', bool $absent = false): array
    {
        return compact('valeur', 'bareme', 'categorie', 'absent');
    }

    // ------------------------------------------------------------ Normalisation

    #[Test]
    public function les_notes_sont_ramenees_sur_20(): void
    {
        $this->assertEqualsWithDelta(15.0, $this->calcul->normaliser(7.5, 10), 1e-12);
        $this->assertEqualsWithDelta(13.0, $this->calcul->normaliser(26, 40), 1e-12);
        $this->assertEqualsWithDelta(12.0, $this->calcul->normaliser(12, 20), 1e-12);
    }

    // --------------------------------------------------------- Moyenne matière

    #[Test]
    public function la_moyenne_matiere_combine_des_baremes_differents(): void
    {
        // 15/20, 8/10 (= 16/20), 30/40 (= 15/20) : moyenne simple du contrôle continu
        $moyenne = $this->calcul->moyenneMatiere([
            $this->note(15, 20),
            $this->note(8, 10),
            $this->note(30, 40),
        ]);

        $this->assertEqualsWithDelta((15 + 16 + 15) / 3, $moyenne, 1e-12);
    }

    #[Test]
    public function la_moyenne_matiere_pondere_controle_continu_et_composition(): void
    {
        // Contrôle continu (10 + 14) / 2 = 12 ; composition 32/40 = 16 → 0,4 × 12 + 0,6 × 16
        $moyenne = $this->calcul->moyenneMatiere([
            $this->note(10),
            $this->note(7, 10),
            $this->note(32, 40, 'composition'),
        ]);

        $this->assertEqualsWithDelta(0.4 * 12 + 0.6 * 16, $moyenne, 1e-12);
    }

    #[Test]
    public function une_categorie_vide_laisse_tout_le_poids_a_l_autre(): void
    {
        $sansComposition = $this->calcul->moyenneMatiere([$this->note(10), $this->note(14)]);
        $compositionSeule = $this->calcul->moyenneMatiere([$this->note(13, 20, 'composition')]);

        $this->assertEqualsWithDelta(12.0, $sansComposition, 1e-12);
        $this->assertEqualsWithDelta(13.0, $compositionSeule, 1e-12);
    }

    #[Test]
    public function une_absence_n_est_comptee_ni_comme_zero_ni_dans_le_diviseur(): void
    {
        $moyenne = $this->calcul->moyenneMatiere([
            $this->note(12),
            $this->note(null, 20, absent: true),
            $this->note(16),
        ]);

        $this->assertEqualsWithDelta(14.0, $moyenne, 1e-12);
    }

    #[Test]
    public function une_composition_manquee_bascule_sur_le_controle_continu(): void
    {
        $moyenne = $this->calcul->moyenneMatiere([
            $this->note(11),
            $this->note(null, 40, 'composition', absent: true),
        ]);

        $this->assertEqualsWithDelta(11.0, $moyenne, 1e-12);
    }

    #[Test]
    public function une_matiere_sans_note_n_a_pas_de_moyenne(): void
    {
        $this->assertNull($this->calcul->moyenneMatiere([]));
        $this->assertNull($this->calcul->moyenneMatiere([$this->note(null, absent: true)]));
    }

    #[Test]
    public function les_ponderations_sont_configurables(): void
    {
        $moitieMoitie = new MoyenneCalculator(['controle_continu' => 0.5, 'composition' => 0.5]);

        $moyenne = $moitieMoitie->moyenneMatiere([$this->note(10), $this->note(16, 20, 'composition')]);

        $this->assertEqualsWithDelta(13.0, $moyenne, 1e-12);
    }

    // -------------------------------------------------------- Moyenne générale

    #[Test]
    public function la_moyenne_generale_est_ponderee_par_les_coefficients(): void
    {
        // (15 × 4 + 10 × 2 + 12 × 1) / 7
        $moyenne = $this->calcul->moyenneGenerale([
            ['moyenne' => 15.0, 'coefficient' => 4],
            ['moyenne' => 10.0, 'coefficient' => 2],
            ['moyenne' => 12.0, 'coefficient' => 1],
        ]);

        $this->assertEqualsWithDelta(92 / 7, $moyenne, 1e-12);
    }

    #[Test]
    public function une_matiere_sans_note_est_exclue_de_la_moyenne_generale(): void
    {
        $moyenne = $this->calcul->moyenneGenerale([
            ['moyenne' => 15.0, 'coefficient' => 4],
            ['moyenne' => null, 'coefficient' => 3],
            ['moyenne' => 9.0, 'coefficient' => 2],
        ]);

        // Le coefficient 3 de la matière non notée ne compte pas au diviseur
        $this->assertEqualsWithDelta((60 + 18) / 6, $moyenne, 1e-12);
    }

    #[Test]
    public function un_eleve_sans_aucune_note_n_a_pas_de_moyenne_generale(): void
    {
        $this->assertNull($this->calcul->moyenneGenerale([['moyenne' => null, 'coefficient' => 2]]));
        $this->assertNull($this->calcul->moyenneGenerale([]));
    }

    #[Test]
    public function les_memes_notes_donnent_des_moyennes_differentes_selon_les_coefficients_du_niveau(): void
    {
        $sixieme = $this->calcul->moyenneGenerale([
            ['moyenne' => 16.0, 'coefficient' => 4], // maths
            ['moyenne' => 8.0, 'coefficient' => 4],  // français
        ]);
        $troisieme = $this->calcul->moyenneGenerale([
            ['moyenne' => 16.0, 'coefficient' => 5],
            ['moyenne' => 8.0, 'coefficient' => 3],
        ]);

        $this->assertEqualsWithDelta(12.0, $sixieme, 1e-12);
        $this->assertEqualsWithDelta(13.0, $troisieme, 1e-12);
    }

    // -------------------------------------------------------------------- Rangs

    #[Test]
    public function les_ex_aequo_partagent_le_rang_et_le_suivant_est_saute(): void
    {
        $rangs = $this->calcul->rangs(['a' => 15.0, 'b' => 12.0, 'c' => 12.0, 'd' => 9.5, 'e' => 12.0, 'f' => 8.0]);

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 2, 'd' => 5, 'e' => 2, 'f' => 6], $rangs);
    }

    #[Test]
    public function le_rang_est_calcule_sur_les_valeurs_non_arrondies(): void
    {
        // 12,344 et 12,3449 s'affichent tous deux « 12,34 » mais ne sont pas ex æquo
        $rangs = $this->calcul->rangs([1 => 12.344, 2 => 12.346, 3 => 12.3449]);

        $this->assertSame([1 => 3, 2 => 1, 3 => 2], $rangs);
    }

    #[Test]
    public function les_erreurs_d_arrondi_des_flottants_ne_cassent_pas_un_ex_aequo(): void
    {
        $this->assertNotSame(0.1 + 0.2, 0.3);

        $this->assertSame([1, 1], $this->calcul->rangs([0.1 + 0.2, 0.3]));
    }

    #[Test]
    public function les_eleves_sans_moyenne_ne_sont_pas_classes(): void
    {
        $this->assertSame([7 => 1, 8 => null, 9 => 2], $this->calcul->rangs([7 => 14.0, 8 => null, 9 => 11.0]));
    }

    #[Test]
    public function une_classe_d_un_seul_eleve_le_classe_premier(): void
    {
        $this->assertSame([42 => 1], $this->calcul->rangs([42 => 7.25]));
        $this->assertSame([], $this->calcul->rangs([]));
    }

    // ------------------------------------------------------------------ Mention

    /** @return array<string, array{float|null, string|null}> */
    public static function mentions(): array
    {
        return [
            '17/20' => [17.0, 'Félicitations'],
            '16/20 pile' => [16.0, 'Félicitations'],
            '15,999/20' => [15.999, 'Tableau d\'honneur avec encouragements'],
            '14/20' => [14.0, 'Tableau d\'honneur avec encouragements'],
            '12,5/20' => [12.5, 'Tableau d\'honneur'],
            '10/20' => [10.0, 'Passable'],
            '9,99/20' => [9.99, 'Avertissement travail'],
            '0/20' => [0.0, 'Avertissement travail'],
            'non classé' => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('mentions')]
    public function la_mention_depend_des_seuils(?float $moyenne, ?string $attendue): void
    {
        $this->assertSame($attendue, $this->calcul->mention($moyenne));
    }

    #[Test]
    public function les_seuils_de_mention_sont_configurables_et_l_ordre_indifferent(): void
    {
        $calcul = new MoyenneCalculator([], 20, [
            ['seuil' => 0, 'libelle' => 'Insuffisant'],
            ['seuil' => 15, 'libelle' => 'Très bien'],
        ]);

        $this->assertSame('Très bien', $calcul->mention(15.5));
        $this->assertSame('Insuffisant', $calcul->mention(14.9));
    }

    // ------------------------------------------------------------- Statistiques

    #[Test]
    public function les_statistiques_ignorent_les_valeurs_nulles(): void
    {
        $this->assertSame(
            ['moyenne' => 12.0, 'max' => 16.0, 'min' => 8.0],
            $this->calcul->statistiques([8.0, null, 16.0, 12.0]),
        );
        $this->assertSame(['moyenne' => null, 'max' => null, 'min' => null], $this->calcul->statistiques([null]));
    }
}
