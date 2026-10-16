<?php

namespace Tests\Feature\Finances;

use App\Enums\Role;
use App\Models\Enseignant;
use App\Models\Frais;
use App\Models\Paiement;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaiementsTest extends TestCase
{
    use ContexteFinances, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
    }

    private function encaisser(int $eleve, array $donnees): TestResponse
    {
        return $this->postJson("/api/inscriptions/{$this->inscriptions[$eleve]->id}/paiements", [
            'date' => '2026-10-16', 'mode' => 'especes', ...$donnees,
        ]);
    }

    // -------------------------------------------------------------- Situation

    public function test_situation_avec_echeancier_et_paiements_annules_exclus(): void
    {
        $this->payer(0, 300);
        $this->payer(0, 200, annuleLe: '2026-09-20 10:00:00');
        $this->connecter(Role::Secretariat);

        $this->getJson("/api/eleves/{$this->inscriptions[0]->eleve_id}/finances")
            ->assertOk()
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.total', 1250.5)
            ->assertJsonPath('data.paye', 300)
            ->assertJsonPath('data.reste', 950.5)
            // 40 % de 1 250,50 = 500,20 exigibles au 30/09 : 200,20 € de retard
            ->assertJsonPath('data.exigible', 500.2)
            ->assertJsonPath('data.retard', 200.2)
            ->assertJsonPath('data.echeances.0.montant', 500.2)
            ->assertJsonPath('data.echeances.0.statut', 'en_retard')
            ->assertJsonPath('data.echeances.1.montant', 375.15)
            ->assertJsonPath('data.echeances.1.statut', 'a_venir')
            // La dernière tranche absorbe l'arrondi : le cumul retombe sur le total
            ->assertJsonPath('data.echeances.2.cumul', 1250.5)
            ->assertJsonCount(2, 'data.frais')
            ->assertJsonCount(2, 'data.paiements')
            ->assertJsonPath('data.paiements.0.annule', true);
    }

    public function test_une_tranche_reglee_n_est_plus_en_retard(): void
    {
        $this->payer(1, 500.20);
        $this->connecter(Role::Secretariat);

        $this->getJson("/api/eleves/{$this->inscriptions[1]->eleve_id}/finances")
            ->assertJsonPath('data.retard', 0)
            ->assertJsonPath('data.echeances.0.statut', 'reglee');
    }

    // ------------------------------------------------------------ Encaissement

    public function test_encaisser_des_tranches_numerote_les_recus(): void
    {
        $this->connecter(Role::Secretariat);

        $this->encaisser(0, ['montant' => 500.20])
            ->assertCreated()
            ->assertJsonPath('data.numero_recu', 'R2026-00001')
            ->assertJsonPath('data.montant', 500.2)
            ->assertJsonPath('data.eleve.nom', 'Albert')
            ->assertJsonPath('data.reference', null);
        $this->encaisser(1, ['montant' => 100, 'mode' => 'cheque', 'reference' => 'CHQ 0012345'])
            ->assertCreated()
            ->assertJsonPath('data.numero_recu', 'R2026-00002')
            ->assertJsonPath('data.mode_libelle', 'Chèque');

        // Le solde exact passe, un centime de plus non
        $this->encaisser(0, ['montant' => 750.31])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['montant' => 'Le montant dépasse le reste à payer (750,30 €).']);
        $this->encaisser(0, ['montant' => 750.30])->assertCreated();
        $this->encaisser(0, ['montant' => 1])->assertConflict()->assertJsonPath('message', 'Les frais de cet élève sont déjà entièrement réglés.');
    }

    public function test_les_donnees_du_paiement_sont_validees(): void
    {
        $this->connecter(Role::Secretariat);

        $this->encaisser(0, ['montant' => 0, 'mode' => 'virement', 'date' => '2026-10-17'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'montant' => 'Le montant doit être positif.',
                'date' => 'La date du paiement ne peut pas être dans le futur.',
                'reference' => 'Indiquez la référence (n° de chèque, de virement ou de transaction).',
            ]);
        $this->encaisser(0, ['montant' => 10, 'mode' => 'bitcoin'])->assertUnprocessable()->assertJsonValidationErrors(['mode' => 'Mode de paiement inconnu.']);
        $this->encaisser(0, ['montant' => 10.123])->assertUnprocessable()->assertJsonValidationErrors('montant');
    }

    public function test_sans_frais_definis_il_n_y_a_rien_a_encaisser(): void
    {
        Frais::query()->delete();
        $this->connecter(Role::Secretariat);

        $this->encaisser(0, ['montant' => 10])->assertConflict();
    }

    // -------------------------------------------------------------- Annulation

    public function test_annuler_un_paiement_le_sort_des_totaux_sans_liberer_son_numero(): void
    {
        $this->connecter(Role::Secretariat);
        $id = $this->encaisser(0, ['montant' => 400])->json('data.id');

        $this->postJson("/api/paiements/{$id}/annuler", [])->assertUnprocessable()->assertJsonValidationErrors(['motif' => 'Indiquez le motif de l\'annulation.']);
        $this->postJson("/api/paiements/{$id}/annuler", ['motif' => 'Chèque rejeté'])
            ->assertOk()
            ->assertJsonPath('data.annule', true)
            ->assertJsonPath('data.motif_annulation', 'Chèque rejeté');
        $this->postJson("/api/paiements/{$id}/annuler", ['motif' => 'Encore'])->assertConflict();

        $this->getJson("/api/eleves/{$this->inscriptions[0]->eleve_id}/finances")->assertJsonPath('data.paye', 0);
        $this->encaisser(0, ['montant' => 400])->assertJsonPath('data.numero_recu', 'R2026-00002');
        $this->assertSame(2, Paiement::count());
    }

    // ------------------------------------------------------------------- Reçus

    public function test_recu_pdf_pour_le_secretariat_et_le_parent_de_l_eleve_uniquement(): void
    {
        $this->connecter(Role::Secretariat);
        $paiement = Paiement::findOrFail($this->encaisser(1, ['montant' => 1250.50])->json('data.id'));

        $recu = $this->get("/api/paiements/{$paiement->id}/recu")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $recu->getContent());
        $this->assertStringContainsString('Recu_R2026-00001_bernard_', $recu->headers->get('content-disposition'));

        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $parent->id])->eleves()->attach($this->inscriptions[1]->eleve_id, ['lien' => 'pere']);
        $this->actingAs($parent, 'sanctum');
        $this->get("/api/paiements/{$paiement->id}/recu")->assertOk();
        $this->getJson("/api/eleves/{$this->inscriptions[1]->eleve_id}/finances")->assertOk()->assertJsonPath('data.reste', 0);

        // Jamais la situation d'un autre enfant
        $this->getJson("/api/eleves/{$this->inscriptions[0]->eleve_id}/finances")->assertForbidden();
        $autre = Paiement::findOrFail($this->payer(0, 10)->id);
        $this->get("/api/paiements/{$autre->id}/recu")->assertForbidden();
    }

    public function test_professeurs_eleves_et_parents_ne_gerent_pas_les_paiements(): void
    {
        $paiement = $this->payer(0, 100);
        $prof = Enseignant::factory()->create()->user;

        foreach ([$prof, User::factory()->create()->assignRole(Role::Eleve->value), User::factory()->create()->assignRole(Role::Parent->value)] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->getJson('/api/paiements')->assertForbidden();
            $this->getJson('/api/impayes')->assertForbidden();
            $this->encaisser(0, ['montant' => 10])->assertForbidden();
            $this->postJson("/api/paiements/{$paiement->id}/annuler", ['motif' => 'x'])->assertForbidden();
            $this->get("/api/paiements/{$paiement->id}/recu")->assertForbidden();
            $this->getJson("/api/eleves/{$this->inscriptions[0]->eleve_id}/finances")->assertForbidden();
        }
    }

    // --------------------------------------------------------- Journal, impayés

    public function test_journal_des_paiements_avec_total_encaisse(): void
    {
        $this->payer(0, 300, date: '2026-09-10');
        $this->payer(1, 200, date: '2026-10-01');
        $this->payer(1, 50, annuleLe: '2026-10-02 09:00:00', date: '2026-10-02');
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/paiements')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.total_encaisse', 500)
            ->assertJsonPath('data.0.date', '2026-10-02');
        $this->getJson('/api/paiements?du=2026-10-01&q=Bernard')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.total_encaisse', 200);
    }

    public function test_impayes_tries_par_retard_avec_totaux(): void
    {
        $this->payer(0, 1250.50);  // Albert : soldé
        $this->payer(1, 600);      // Bernard : tranche 1 réglée, reste 650,50
        // Durand : rien payé, 500,20 € de retard
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/impayes')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.eleve.nom', 'Durand')
            ->assertJsonPath('data.0.retard', 500.2)
            ->assertJsonPath('data.0.reste', 1250.5)
            ->assertJsonPath('data.1.eleve.nom', 'Bernard')
            ->assertJsonPath('data.1.retard', 0)
            ->assertJsonPath('meta.totaux.attendu', 3751.5)
            ->assertJsonPath('meta.totaux.encaisse', 1850.5)
            ->assertJsonPath('meta.totaux.reste', 1901)
            ->assertJsonPath('meta.totaux.retard', 500.2)
            ->assertJsonPath('meta.totaux.en_retard', 1)
            ->assertJsonPath('meta.totaux.pourcentage_exigible', 40);

        $this->getJson('/api/impayes?statut=en_retard')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/impayes?statut=solde')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.eleve.nom', 'Albert');
        $this->getJson('/api/impayes?statut=tous&q=Bern')->assertJsonPath('meta.total', 1);
    }
}
