<?php

namespace Tests\Feature\Finances;

use App\Enums\Role;
use App\Models\Tuteur;
use App\Models\User;
use App\Notifications\RecapRetards;
use App\Notifications\RetardEcolage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Au 16/10/2026, la tranche 1 (40 % de 1 250,50 = 500,20 €) est échue depuis le 30/09 :
 * Albert a tout réglé, Bernard a payé 300 € (200,20 € de retard), Durand rien (500,20 €).
 */
class RelancesTest extends TestCase
{
    use ContexteFinances, RefreshDatabase;

    private User $parentBernard;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->payer(0, 1250.50);
        $this->payer(1, 300);

        $this->parentBernard = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $this->parentBernard->id])->eleves()->attach($this->inscriptions[1]->eleve_id, ['lien' => 'pere']);
        $this->directeur = User::factory()->create()->assignRole(Role::Admin->value);
    }

    public function test_les_parents_et_la_direction_sont_avertis_des_retards(): void
    {
        Notification::fake();
        $secretaire = User::factory()->create()->assignRole(Role::Secretariat->value);
        $professeur = User::factory()->create()->assignRole(Role::Professeur->value);

        $this->artisan('scolaris:relancer-impayes')->expectsOutput('2 famille(s) avertie(s).')->assertSuccessful();

        Notification::assertSentTo($this->parentBernard, RetardEcolage::class, fn (RetardEcolage $n) => $n->tranche === 'Tranche 1'
            && $n->dateLimite === '30/09/2026'
            && $n->montantRetard === '200,20 €');
        Notification::assertSentTo([$this->directeur, $secretaire], RecapRetards::class, fn (RecapRetards $n) => count($n->eleves) === 2
            && $n->montantTotal === '700,40 €');
        Notification::assertNotSentTo($professeur, RecapRetards::class);
        $this->assertDatabaseCount('relances_impayes', 2);
    }

    public function test_une_famille_n_est_avertie_qu_une_fois_par_tranche(): void
    {
        $this->artisan('scolaris:relancer-impayes')->assertSuccessful();
        Notification::fake();

        $this->artisan('scolaris:relancer-impayes')->expectsOutput('Aucun nouveau retard.')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_avant_la_premiere_date_limite_personne_n_est_averti(): void
    {
        Notification::fake();
        $this->travelTo('2026-09-20');

        $this->artisan('scolaris:relancer-impayes')->expectsOutput('Aucun nouveau retard.');
        Notification::assertNothingSent();
    }

    public function test_le_parent_consulte_et_lit_ses_notifications(): void
    {
        $this->artisan('scolaris:relancer-impayes');
        $this->actingAs($this->parentBernard, 'sanctum');

        $id = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.non_lues', 1)
            ->assertJsonPath('data.0.type', 'retard_ecolage')
            ->assertJsonPath('data.0.lien', '/paiements')
            ->assertJsonPath('data.0.lu', false)
            ->json('data.0.id');

        $this->postJson("/api/notifications/{$id}/lire")->assertNoContent();
        $this->getJson('/api/notifications')->assertJsonPath('meta.non_lues', 0)->assertJsonPath('data.0.lu', true);

        // Les notifications d'un autre utilisateur restent inaccessibles
        $autre = $this->directeur->notifications()->first();
        $this->postJson("/api/notifications/{$autre->id}/lire")->assertNotFound();
    }

    public function test_la_direction_marque_tout_comme_lu(): void
    {
        $this->artisan('scolaris:relancer-impayes');
        $this->actingAs($this->directeur, 'sanctum');

        $this->getJson('/api/notifications')->assertJsonPath('meta.non_lues', 1)->assertJsonPath('data.0.type', 'recap_retards');
        $this->postJson('/api/notifications/tout-lire')->assertNoContent();
        $this->getJson('/api/notifications')->assertJsonPath('meta.non_lues', 0);
    }
}
