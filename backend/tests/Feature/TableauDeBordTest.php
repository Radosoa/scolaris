<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Absence;
use App\Models\Affectation;
use App\Models\Appel;
use App\Models\Bulletin;
use App\Models\Creneau;
use App\Models\Periode;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finances\ContexteFinances;
use Tests\TestCase;

/**
 * Au 16/10/2026 (vendredi), 6e A : Albert (M), Bernard (M), Durand (F) — voir ContexteFinances.
 */
class TableauDeBordTest extends TestCase
{
    use ContexteFinances, RefreshDatabase;

    private Creneau $lundi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->inscriptions[2]->eleve->update(['sexe' => 'F']);
        foreach ([0, 1] as $i) {
            $this->inscriptions[$i]->eleve->update(['sexe' => 'M']);
        }

        // Bulletins du T1 : 14 et 8 de moyenne, Durand non classé
        $periode = Periode::factory()->for($this->annee)->create(['date_debut' => '2026-09-01', 'date_fin' => '2026-11-30']);
        foreach ([[0, 14.0, 1], [1, 8.0, 2], [2, null, null]] as [$i, $moyenne, $rang]) {
            $bulletin = new Bulletin(['inscription_id' => $this->inscriptions[$i]->id, 'periode_id' => $periode->id, 'moyenne' => $moyenne, 'rang' => $rang, 'effectif' => 3]);
            $bulletin->forceFill(['valide' => true])->save();
        }

        // Deux appels du lundi 8 h – 10 h (2 h × 3 élèves = 6 heures-élèves chacun) et 2 h d'absence
        $affectation = Affectation::factory()->for($this->classe)->create();
        $this->lundi = Creneau::create(['affectation_id' => $affectation->id, 'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00']);
        foreach (['2026-10-05', '2026-10-12'] as $date) {
            Appel::create(['creneau_id' => $this->lundi->id, 'date' => $date]);
        }
        Absence::create(['eleve_id' => $this->inscriptions[0]->eleve_id, 'creneau_id' => $this->lundi->id, 'date' => '2026-10-12', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'type' => 'absence']);

        $this->payer(0, 1250.50, date: '2026-09-10');
        $this->payer(1, 300, date: '2026-10-01');
    }

    public function test_tableau_de_bord_de_la_direction(): void
    {
        $this->connecter(Role::Admin);

        $this->getJson('/api/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.type', 'direction')
            ->assertJsonPath('data.compteurs.eleves', 3)
            ->assertJsonPath('data.compteurs.classes', 1)
            ->assertJsonPath('data.effectifs.0.classe.nom', '6e A')
            ->assertJsonPath('data.effectifs.0.garcons', 2)
            ->assertJsonPath('data.effectifs.0.filles', 1)
            // Résultats : moyenne (14 + 8) / 2, un élève sur deux classés au-dessus de 10
            ->assertJsonPath('data.resultats.periode.libelle', 'Trimestre 1')
            ->assertJsonPath('data.resultats.classes.0.moyenne', 11)
            ->assertJsonPath('data.resultats.classes.0.taux_reussite', 50)
            ->assertJsonPath('data.resultats.taux_reussite', 50)
            // Absentéisme : 2 h d'absence sur 12 heures-élèves attendues
            ->assertJsonPath('data.absenteisme.heures_non_justifiees', 2)
            ->assertJsonPath('data.absenteisme.taux', 16.67)
            ->assertJsonPath('data.absenteisme.par_semaine.0.semaine', '2026-10-12')
            // Finances : 3 × 1 250,50 attendus, 1 550,50 encaissés, sur deux mois
            ->assertJsonPath('data.finances.attendu', 3751.5)
            ->assertJsonPath('data.finances.encaisse', 1550.5)
            ->assertJsonPath('data.finances.par_mois.0.mois', '2026-09')
            ->assertJsonPath('data.finances.par_mois.1.montant', 300);
    }

    public function test_tableau_de_bord_du_secretariat(): void
    {
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.type', 'secretariat')
            ->assertJsonPath('data.absences.a_justifier', 1)
            ->assertJsonPath('data.encaissements_7_jours.nombre', 0)
            ->assertJsonPath('data.finances.en_retard', 2)
            ->assertJsonCount(2, 'data.derniers_paiements');
    }

    public function test_tableau_de_bord_du_professeur_avec_les_appels_oublies(): void
    {
        $this->actingAs($this->lundi->affectation->enseignant->user, 'sanctum');

        // Lundis des 14 derniers jours : 05/10 et 12/10 faits ; aucun autre cours
        $this->getJson('/api/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.type', 'professeur')
            ->assertJsonCount(0, 'data.cours_du_jour')
            ->assertJsonCount(0, 'data.appels_en_attente')
            ->assertJsonPath('data.classes.0.classe', '6e A');

        Appel::where('date', '2026-10-12')->delete();
        $this->getJson('/api/tableau-de-bord')
            ->assertJsonCount(1, 'data.appels_en_attente')
            ->assertJsonPath('data.appels_en_attente.0.date', '2026-10-12');
    }

    public function test_tableau_de_bord_de_l_eleve_et_du_parent(): void
    {
        $user = User::factory()->create()->assignRole(Role::Eleve->value);
        $this->inscriptions[0]->eleve->user()->associate($user)->save();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.type', 'eleve')
            ->assertJsonPath('data.eleve.dernier_bulletin.moyenne', 14)
            ->assertJsonPath('data.eleve.dernier_bulletin.rang', 1)
            ->assertJsonPath('data.eleve.absences.heures_non_justifiees', 2)
            ->assertJsonPath('data.eleve.finances', null);

        // Parent de Bernard uniquement : pas d'accès aux données d'Albert
        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $parent->id])->eleves()->attach($this->inscriptions[1]->eleve_id, ['lien' => 'pere']);
        $this->actingAs($parent, 'sanctum');

        $this->getJson('/api/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.type', 'parent')
            ->assertJsonCount(1, 'data.enfants')
            ->assertJsonPath('data.enfants.0.nom_complet', $this->inscriptions[1]->eleve->nomComplet())
            ->assertJsonPath('data.enfants.0.finances.paye', 300)
            ->assertJsonPath('data.enfants.0.finances.retard', 200.2);
    }

    public function test_un_compte_sans_role_n_a_pas_de_tableau_de_bord(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson('/api/tableau-de-bord')->assertForbidden();
    }
}
