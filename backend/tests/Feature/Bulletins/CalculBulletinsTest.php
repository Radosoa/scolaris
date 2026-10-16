<?php

namespace Tests\Feature\Bulletins;

use App\Exceptions\RegleMetierException;
use App\Http\Resources\BulletinResource;
use App\Models\Absence;
use App\Models\Affectation;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Niveau;
use App\Models\Periode;
use App\Models\User;
use App\Services\Bulletins\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** BulletinService de bout en bout : notes en base → bulletins et lignes enregistrés. */
class CalculBulletinsTest extends TestCase
{
    use ContexteBulletins, RefreshDatabase;

    private BulletinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->service = app(BulletinService::class);
    }

    /** Bulletin d'un élève du contexte (index 0 = Albert, 1 = Bernard, 2 = Durand). */
    private function bulletin(int $eleve): Bulletin
    {
        return Bulletin::whereHas('inscription', fn ($q) => $q->where('eleve_id', $this->eleves[$eleve]->id))
            ->where('periode_id', $this->trimestre->id)
            ->with('lignes.matiere')
            ->firstOrFail();
    }

    private function ligne(Bulletin $bulletin, string $code): ?BulletinLigne
    {
        return $bulletin->lignes->first(fn ($l) => $l->matiere->code === $code);
    }

    public function test_calcul_complet_avec_baremes_differents_composition_et_coefficients(): void
    {
        // Maths : devoir /20, interrogation /10, composition /40
        $this->evaluer($this->aff['maths'], [0 => 14, 1 => 8, 2 => 10]);
        $this->evaluer($this->aff['maths'], [0 => 9, 1 => 5, 2 => 6], bareme: 10);
        $this->evaluer($this->aff['maths'], [0 => 36, 1 => 20, 2 => 24], 'composition', 40);
        // Français : devoir + composition /20
        $this->evaluer($this->aff['francais'], [0 => 12, 1 => 15, 2 => 11]);
        $this->evaluer($this->aff['francais'], [0 => 13, 1 => 14, 2 => 9], 'composition');
        // Anglais : un seul devoir
        $this->evaluer($this->aff['anglais'], [0 => 16, 1 => 10, 2 => 12]);

        $bulletins = $this->service->calculer($this->classe, $this->trimestre);
        $this->assertCount(3, $bulletins);

        // Albert — maths : CC (14 + 18) / 2 = 16 ; compo 36/40 = 18 → 0,4 × 16 + 0,6 × 18 = 17,2
        //          français : 0,4 × 12 + 0,6 × 13 = 12,6 ; anglais : 16
        //          générale : (17,2 × 4 + 12,6 × 3 + 16 × 2) / 9 = 138,6 / 9 = 15,4
        $albert = $this->bulletin(0);
        $this->assertEqualsWithDelta(17.2, $this->ligne($albert, 'MATH')->moyenne, 1e-4);
        $this->assertEqualsWithDelta(12.6, $this->ligne($albert, 'FR')->moyenne, 1e-4);
        $this->assertEqualsWithDelta(16.0, $this->ligne($albert, 'ANG')->moyenne, 1e-4);
        $this->assertEquals(4, $this->ligne($albert, 'MATH')->coefficient);
        $this->assertEqualsWithDelta(15.4, $albert->moyenne, 1e-4);
        $this->assertSame('Tableau d\'honneur avec encouragements', $albert->mention);
        $this->assertSame(1, $albert->rang);
        $this->assertSame(3, $albert->effectif);

        // Bernard — maths : CC (8 + 10) / 2 = 9 ; compo 10 → 9,6 ; français 0,4 × 15 + 0,6 × 14 = 14,4 ;
        //           anglais 10 → (38,4 + 43,2 + 20) / 9 = 11,288…
        $bernard = $this->bulletin(1);
        $this->assertEqualsWithDelta(101.6 / 9, $bernard->moyenne, 1e-4);
        $this->assertSame('Passable', $bernard->mention);
        // Durand — maths : CC (10 + 12) / 2 = 11 ; compo 12 → 11,6 ; français 0,4 × 11 + 0,6 × 9 = 9,8 ;
        //          anglais 12 → (46,4 + 29,4 + 24) / 9 = 11,088…
        $durand = $this->bulletin(2);
        $this->assertEqualsWithDelta(99.8 / 9, $durand->moyenne, 1e-4);
        $this->assertSame([2, 3], [$bernard->rang, $durand->rang]);

        // Rangs et statistiques par matière
        $this->assertSame(1, $this->ligne($albert, 'MATH')->rang_matiere);
        $this->assertSame(3, $this->ligne($bernard, 'MATH')->rang_matiere);
        $this->assertSame(1, $this->ligne($bernard, 'FR')->rang_matiere);
        $this->assertEqualsWithDelta((17.2 + 9.6 + 11.6) / 3, $this->ligne($durand, 'MATH')->moyenne_classe, 1e-4);
        $this->assertEqualsWithDelta(17.2, $this->ligne($durand, 'MATH')->moyenne_max, 1e-4);
        $this->assertEqualsWithDelta(9.6, $this->ligne($durand, 'MATH')->moyenne_min, 1e-4);

        // Statistiques de la classe, identiques sur chaque bulletin
        $this->assertEqualsWithDelta((138.6 + 101.6 + 99.8) / 27, $durand->moyenne_classe, 1e-4);
        $this->assertEqualsWithDelta(15.4, $durand->moyenne_max, 1e-4);
        $this->assertEqualsWithDelta(99.8 / 9, $albert->moyenne_min, 1e-4);
    }

    public function test_un_eleve_absent_a_une_evaluation_n_est_pas_penalise(): void
    {
        $this->evaluer($this->aff['maths'], [0 => 12, 1 => 12]);
        $this->evaluer($this->aff['maths'], [0 => null, 1 => 0]); // Albert absent, Bernard 0

        $this->service->calculer($this->classe, $this->trimestre);

        $this->assertEqualsWithDelta(12.0, $this->bulletin(0)->moyenne, 1e-4);
        $this->assertEqualsWithDelta(6.0, $this->bulletin(1)->moyenne, 1e-4);
    }

    public function test_une_matiere_sans_note_est_exclue_de_la_moyenne_generale(): void
    {
        $this->evaluer($this->aff['maths'], [0 => 16, 1 => 8, 2 => 10]);
        $this->evaluer($this->aff['francais'], [0 => 10, 1 => 14]); // Durand non noté en français
        // Anglais : aucune évaluation pour personne

        $this->service->calculer($this->classe, $this->trimestre);

        $albert = $this->bulletin(0);
        $this->assertNull($this->ligne($albert, 'ANG')->moyenne);
        $this->assertNull($this->ligne($albert, 'ANG')->rang_matiere);
        $this->assertNull($this->ligne($albert, 'ANG')->moyenne_classe);
        $this->assertEqualsWithDelta((16 * 4 + 10 * 3) / 7, $albert->moyenne, 1e-4);

        $durand = $this->bulletin(2);
        $this->assertNull($this->ligne($durand, 'FR')->moyenne);
        $this->assertEqualsWithDelta(10.0, $durand->moyenne, 1e-4); // maths seules
        // Les autres élèves sont classés en français sans lui
        $this->assertSame(1, $this->ligne($this->bulletin(1), 'FR')->rang_matiere);
    }

    public function test_les_ex_aequo_partagent_le_rang(): void
    {
        $quatrieme = $this->inscrire($this->classe, 'Martin');
        $eleves = [...$this->eleves, $quatrieme];
        $this->evaluer($this->aff['maths'], [0 => 15, 1 => 12, 2 => 12, 3 => 9], eleves: $eleves);

        $this->service->calculer($this->classe, $this->trimestre);

        $rangs = Bulletin::deClasse($this->classe, $this->trimestre)->with('inscription.eleve')->get()
            ->mapWithKeys(fn (Bulletin $b) => [$b->inscription->eleve->nom => $b->rang])
            ->sortKeys()->all();
        $this->assertSame(['Albert' => 1, 'Bernard' => 2, 'Durand' => 2, 'Martin' => 4], $rangs);
        $this->assertTrue($this->bulletin(1)->estExAequo());
        $this->assertFalse($this->bulletin(0)->estExAequo());
    }

    public function test_les_rangs_utilisent_les_moyennes_non_arrondies(): void
    {
        // Albert : (12 + 12 + 12 + 12,01) / 4 = 12,0025 ; Bernard : 12 → tous deux affichés « 12,00 »
        foreach ([12, 12, 12, 12.01] as $note) {
            $this->evaluer($this->aff['maths'], [0 => $note, 1 => 12]);
        }

        $this->service->calculer($this->classe, $this->trimestre);

        $albert = $this->bulletin(0);
        $bernard = $this->bulletin(1);
        $this->assertSame(BulletinResource::arrondi($albert->moyenne), BulletinResource::arrondi($bernard->moyenne));
        // … mais Albert est devant : pas d'ex æquo
        $this->assertSame([1, 2], [$albert->rang, $bernard->rang]);
    }

    public function test_une_classe_d_un_seul_eleve(): void
    {
        $classe = Classe::factory()->for($this->annee)->create(['nom' => '3e Unique']);
        Coefficient::factory()->create(['niveau_id' => $classe->niveau_id, 'matiere_id' => $this->aff['maths']->matiere_id, 'valeur' => 5]);
        $affectation = Affectation::factory()->for($classe)->create(['matiere_id' => $this->aff['maths']->matiere_id]);
        $seul = $this->inscrire($classe, 'Seul');
        $this->evaluer($affectation, [0 => 7], eleves: [$seul]);

        [$bulletin] = $this->service->calculer($classe, $this->trimestre)->all();

        $this->assertSame(1, $bulletin->rang);
        $this->assertSame(1, $bulletin->effectif);
        $this->assertEqualsWithDelta(7.0, $bulletin->moyenne, 1e-4);
        $this->assertEqualsWithDelta(7.0, $bulletin->moyenne_classe, 1e-4);
        $this->assertSame('Avertissement travail', $bulletin->mention);
    }

    public function test_les_coefficients_dependent_du_niveau_de_la_classe(): void
    {
        // Mêmes notes en 6e A (maths 4, français 3) et en 3e A (maths 2, français 5)
        $troisieme = Classe::factory()->for($this->annee)->for(Niveau::factory()->state(['libelle' => '3e', 'ordre' => 4]))->create(['nom' => '3e A']);
        $affs = [];
        foreach (['maths' => 2, 'francais' => 5] as $cle => $coef) {
            Coefficient::factory()->create(['niveau_id' => $troisieme->niveau_id, 'matiere_id' => $this->aff[$cle]->matiere_id, 'valeur' => $coef]);
            $affs[$cle] = Affectation::factory()->for($troisieme)->create(['matiere_id' => $this->aff[$cle]->matiere_id]);
        }
        $eleve3e = $this->inscrire($troisieme, 'Lefèvre');

        $this->evaluer($this->aff['maths'], [0 => 18]);
        $this->evaluer($this->aff['francais'], [0 => 4]);
        $this->evaluer($affs['maths'], [0 => 18], eleves: [$eleve3e]);
        $this->evaluer($affs['francais'], [0 => 4], eleves: [$eleve3e]);

        $this->service->calculer($this->classe, $this->trimestre);
        [$bulletin3e] = $this->service->calculer($troisieme, $this->trimestre)->all();

        $this->assertEqualsWithDelta((18 * 4 + 4 * 3) / 7, $this->bulletin(0)->moyenne, 1e-4); // 12
        $this->assertEqualsWithDelta((18 * 2 + 4 * 5) / 7, $bulletin3e->moyenne, 1e-4);       // 8
    }

    public function test_un_eleve_sans_aucune_note_n_est_pas_classe_mais_compte_dans_l_effectif(): void
    {
        $this->evaluer($this->aff['maths'], [0 => 14, 1 => 10]);

        $this->service->calculer($this->classe, $this->trimestre);

        $durand = $this->bulletin(2);
        $this->assertNull($durand->moyenne);
        $this->assertNull($durand->rang);
        $this->assertNull($durand->mention);
        $this->assertSame(3, $durand->effectif);
        $this->assertEqualsWithDelta(12.0, $durand->moyenne_classe, 1e-4); // moyenne des seuls élèves notés
    }

    public function test_les_heures_d_absence_et_les_retards_de_la_periode_sont_totalises(): void
    {
        $albert = $this->eleves[0]->id;
        foreach ([
            ['2025-09-15', '08:00', '10:00', 'absence', true],
            ['2025-10-02', '14:00', '15:30', 'absence', false],
            ['2025-10-03', '08:00', '09:00', 'absence', false],
            ['2025-10-06', '08:00', '08:10', 'retard', false],
            ['2025-12-05', '08:00', '12:00', 'absence', false], // hors période
        ] as [$date, $debut, $fin, $type, $justifiee]) {
            Absence::create(['eleve_id' => $albert, 'date' => $date, 'heure_debut' => $debut, 'heure_fin' => $fin, 'type' => $type, 'justifiee' => $justifiee]);
        }

        $this->service->calculer($this->classe, $this->trimestre);

        $bulletin = $this->bulletin(0);
        $this->assertEquals(2.0, $bulletin->heures_abs_justifiees);
        $this->assertEquals(2.5, $bulletin->heures_abs_non_justifiees);
        $this->assertSame(1, $bulletin->nb_retards);
        $this->assertEquals(0, $this->bulletin(1)->heures_abs_non_justifiees);
    }

    public function test_le_calcul_est_refuse_si_la_periode_n_est_pas_cloturee(): void
    {
        $this->trimestre->forceFill(['cloturee' => false])->save();

        $this->expectException(RegleMetierException::class);
        $this->service->calculer($this->classe, $this->trimestre);
    }

    public function test_la_periode_doit_appartenir_a_l_annee_de_la_classe(): void
    {
        $autreAnnee = Periode::factory()->cloturee()->create();

        $this->expectException(ValidationException::class);
        $this->service->calculer($this->classe, $autreAnnee);
    }

    public function test_un_recalcul_met_a_jour_les_moyennes_et_conserve_les_appreciations(): void
    {
        $devoir = $this->evaluer($this->aff['maths'], [0 => 10, 1 => 12, 2 => 14]);
        $this->service->calculer($this->classe, $this->trimestre);

        $bulletin = $this->bulletin(0);
        $this->service->definirAppreciationGenerale($bulletin, 'Peut mieux faire.');
        $this->ligne($bulletin, 'MATH')->forceFill(['appreciation' => 'Des progrès.'])->save();

        $devoir->notes()->where('eleve_id', $this->eleves[0]->id)->update(['valeur' => 18]);
        $this->service->calculer($this->classe, $this->trimestre);

        $bulletin = $this->bulletin(0);
        $this->assertEqualsWithDelta(18.0, $bulletin->moyenne, 1e-4);
        $this->assertSame(1, $bulletin->rang);
        $this->assertSame('Peut mieux faire.', $bulletin->appreciation_generale);
        $this->assertSame('Des progrès.', $this->ligne($bulletin, 'MATH')->appreciation);
        $this->assertSame(3, Bulletin::count());
    }

    public function test_un_eleve_sorti_de_la_classe_perd_son_bulletin_brouillon_au_recalcul(): void
    {
        $this->evaluer($this->aff['maths'], [0 => 10, 1 => 12, 2 => 14]);
        $this->service->calculer($this->classe, $this->trimestre);

        $this->eleves[2]->inscriptions()->update(['statut' => 'transfere']);
        $this->service->calculer($this->classe, $this->trimestre);

        $this->assertSame(2, Bulletin::deClasse($this->classe, $this->trimestre)->count());
        $this->assertSame(2, $this->bulletin(0)->effectif);
    }

    public function test_un_bulletin_valide_n_est_plus_modifiable(): void
    {
        $this->evaluer($this->aff['maths'], [0 => 10, 1 => 12, 2 => 14]);
        $this->service->calculer($this->classe, $this->trimestre);
        $admin = User::factory()->create();

        $this->assertSame(3, $this->service->valider($this->classe, $this->trimestre, $admin));
        $bulletin = $this->bulletin(0);
        $this->assertTrue($bulletin->valide);
        $this->assertSame($admin->id, $bulletin->valide_par);

        foreach ([
            fn () => $this->service->calculer($this->classe, $this->trimestre),
            fn () => $this->service->valider($this->classe, $this->trimestre, $admin),
            fn () => $this->service->definirAppreciationGenerale($bulletin, 'Trop tard'),
            fn () => $this->service->enregistrerAppreciations($this->aff['maths'], $this->trimestre, [
                ['ligne_id' => $this->ligne($bulletin, 'MATH')->id, 'appreciation' => 'Trop tard'],
            ]),
        ] as $action) {
            try {
                $action();
                $this->fail('Une action sur un bulletin validé aurait dû être refusée.');
            } catch (RegleMetierException) {
                // attendu
            }
        }

        $this->assertNull($this->bulletin(0)->appreciation_generale);
    }

    public function test_on_ne_valide_pas_des_bulletins_non_calcules(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->service->valider($this->classe, $this->trimestre, User::factory()->create());
    }

    public function test_une_classe_sans_eleve_ne_peut_pas_etre_calculee(): void
    {
        $vide = Classe::factory()->for($this->annee)->create();

        $this->expectException(RegleMetierException::class);
        $this->service->calculer($vide, $this->trimestre);
    }
}
