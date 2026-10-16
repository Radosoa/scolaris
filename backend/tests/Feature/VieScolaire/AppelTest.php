<?php

namespace Tests\Feature\VieScolaire;

use App\Enums\Role;
use App\Models\Absence;
use App\Models\Appel;
use App\Models\Bulletin;
use App\Models\Coefficient;
use App\Models\Creneau;
use App\Models\Periode;
use App\Services\Bulletins\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppelTest extends TestCase
{
    use ContexteVieScolaire, RefreshDatabase;

    private Creneau $lundi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->lundi = $this->creneau($this->mathsA, 1, '08:00', '10:00', 'Salle 12');
        $this->actingAs($this->prof->user, 'sanctum');
    }

    /** @param  array<int, array{0: string, 1?: int}>  $statuts  [index élève => [statut, minutes]] */
    private function faireAppel(array $statuts, string $date = self::LUNDI): TestResponse
    {
        return $this->putJson("/api/creneaux/{$this->lundi->id}/appel", [
            'date' => $date,
            'eleves' => collect($statuts)->map(fn (array $s, int $i) => [
                'eleve_id' => $this->eleves[$i]->id, 'statut' => $s[0], 'minutes_retard' => $s[1] ?? null,
            ])->values()->all(),
        ]);
    }

    public function test_le_professeur_voit_ses_cours_du_jour_avec_l_etat_de_l_appel(): void
    {
        $this->creneau($this->mathsB, 1, '10:00', '11:00');
        $this->creneau($this->francaisA, 1, '10:00', '11:00'); // cours d'une collègue
        $this->creneau($this->mathsA, 2, '08:00', '09:00');    // un mardi

        $this->getJson('/api/appels?date='.self::LUNDI)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.creneau.classe.nom', '6e A')
            ->assertJsonPath('data.0.appel_fait', false)
            ->assertJsonPath('data.1.creneau.classe.nom', '6e B');

        $this->faireAppel([]);
        $this->getJson('/api/appels?date='.self::LUNDI)->assertJsonPath('data.0.appel_fait', true);
    }

    public function test_feuille_d_appel_par_defaut_tout_le_monde_est_present(): void
    {
        $this->getJson("/api/creneaux/{$this->lundi->id}/appel?date=".self::LUNDI)
            ->assertOk()
            ->assertJsonPath('data.appel', null)
            ->assertJsonPath('data.creneau.matiere.nom', 'Mathématiques')
            ->assertJsonCount(3, 'data.eleves')
            ->assertJsonPath('data.eleves.0.nom', 'Albert')
            ->assertJsonPath('data.eleves.0.statut', 'present');
    }

    public function test_l_appel_enregistre_absences_et_retards(): void
    {
        $this->faireAppel([0 => ['absent'], 1 => ['retard', 15], 2 => ['present']])
            ->assertOk()
            ->assertJsonPath('data.appel.fait_par', $this->prof->user->name)
            ->assertJsonPath('data.eleves.0.statut', 'absent')
            ->assertJsonPath('data.eleves.1.statut', 'retard')
            ->assertJsonPath('data.eleves.1.minutes_retard', 15)
            ->assertJsonPath('data.eleves.2.statut', 'present');

        $absence = Absence::where('eleve_id', $this->eleves[0]->id)->firstOrFail();
        $this->assertSame(['08:00', '10:00', 'absence', false], [$absence->heure_debut, $absence->heure_fin, $absence->type->value, $absence->justifiee]);
        $this->assertEquals(2.0, $absence->dureeEnHeures());
        $this->assertSame($this->prof->user->id, $absence->saisie_par);

        $retard = Absence::where('eleve_id', $this->eleves[1]->id)->firstOrFail();
        $this->assertSame(['08:00', '08:15', 'retard'], [$retard->heure_debut, $retard->heure_fin, $retard->type->value]);
        $this->assertSame(2, Absence::count());
        $this->assertSame(1, Appel::count());
    }

    public function test_corriger_l_appel_supprime_ou_modifie_les_absences(): void
    {
        $this->faireAppel([0 => ['absent'], 1 => ['absent']]);
        // La vie scolaire justifie l'absence de Bernard
        Absence::where('eleve_id', $this->eleves[1]->id)->update(['justifiee' => true, 'motif' => 'Rendez-vous médical']);

        // Albert était finalement présent, Bernard reste absent, Durand arrive en retard
        $this->faireAppel([0 => ['present'], 1 => ['absent'], 2 => ['retard', 5]])->assertOk();

        $this->assertFalse(Absence::where('eleve_id', $this->eleves[0]->id)->exists());
        $bernard = Absence::where('eleve_id', $this->eleves[1]->id)->firstOrFail();
        $this->assertTrue($bernard->justifiee, 'La justification est conservée si le statut ne change pas');
        $this->assertSame('Rendez-vous médical', $bernard->motif);

        // Un changement de statut remet la justification à zéro
        $this->faireAppel([1 => ['retard', 10]])->assertOk();
        $bernard->refresh();
        $this->assertSame('retard', $bernard->type->value);
        $this->assertFalse($bernard->justifiee);
        $this->assertNull($bernard->motif);
        // Les élèves non transmis sont présents
        $this->assertFalse(Absence::where('eleve_id', $this->eleves[2]->id)->exists());
        $this->assertSame(1, Appel::count());
    }

    public function test_la_date_doit_correspondre_au_cours(): void
    {
        $this->faireAppel([], '2025-09-09')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Ce cours a lieu le Lundi : choisissez un Lundi.']);
        $this->faireAppel([], '2027-09-06')->assertUnprocessable()->assertJsonValidationErrors('date');      // à venir
        $this->faireAppel([], '2025-08-25')->assertUnprocessable()->assertJsonValidationErrors('date');      // avant la rentrée
        $this->getJson("/api/creneaux/{$this->lundi->id}/appel?date=2025-09-10")->assertUnprocessable();
    }

    public function test_les_lignes_de_l_appel_sont_controlees(): void
    {
        $this->putJson("/api/creneaux/{$this->lundi->id}/appel", ['date' => self::LUNDI, 'eleves' => [
            ['eleve_id' => $this->eleveB->id, 'statut' => 'absent'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['eleves.0.eleve_id' => 'Cet élève n\'est pas inscrit dans la classe.']);

        $this->faireAppel([0 => ['retard']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['eleves.0.minutes_retard' => 'Indiquez la durée du retard.']);

        $this->faireAppel([0 => ['retard', 150]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['eleves.0.minutes_retard' => 'Le retard dépasse la durée du cours : marquez l\'élève absent.']);

        $this->faireAppel([0 => ['malade']])->assertUnprocessable()->assertJsonValidationErrors('eleves.0.statut');
        $this->assertSame(0, Absence::count());
    }

    public function test_un_professeur_ne_fait_l_appel_que_de_ses_cours(): void
    {
        $francais = $this->creneau($this->francaisA, 1, '10:00', '11:00');

        $this->getJson("/api/creneaux/{$francais->id}/appel?date=".self::LUNDI)->assertForbidden();
        $this->putJson("/api/creneaux/{$francais->id}/appel", ['date' => self::LUNDI, 'eleves' => []])->assertForbidden();

        foreach ([Role::Secretariat, Role::Eleve, Role::Parent] as $role) {
            $this->connecter($role);
            $this->getJson("/api/creneaux/{$this->lundi->id}/appel?date=".self::LUNDI)->assertForbidden();
            $this->getJson('/api/appels')->assertForbidden();
        }
    }

    public function test_les_absences_de_l_appel_alimentent_le_bulletin(): void
    {
        $this->faireAppel([0 => ['absent'], 1 => ['retard', 10]]);
        Absence::where('eleve_id', $this->eleves[0]->id)->update(['justifiee' => true, 'motif' => 'Maladie']);
        $this->putJson("/api/creneaux/{$this->lundi->id}/appel", ['date' => '2025-09-15', 'eleves' => [
            ['eleve_id' => $this->eleves[0]->id, 'statut' => 'absent'],
        ]])->assertOk();

        $trimestre = Periode::factory()->for($this->annee)->cloturee()->create(['date_debut' => '2025-09-01', 'date_fin' => '2025-11-30']);
        Coefficient::factory()->create(['niveau_id' => $this->classeA->niveau_id, 'matiere_id' => $this->mathsA->matiere_id, 'valeur' => 4]);
        app(BulletinService::class)->calculer($this->classeA, $trimestre);

        $albert = Bulletin::whereHas('inscription', fn ($q) => $q->where('eleve_id', $this->eleves[0]->id))->firstOrFail();
        $this->assertEquals(2.0, $albert->heures_abs_justifiees);
        $this->assertEquals(2.0, $albert->heures_abs_non_justifiees);
        $bernard = Bulletin::whereHas('inscription', fn ($q) => $q->where('eleve_id', $this->eleves[1]->id))->firstOrFail();
        $this->assertSame(1, $bernard->nb_retards);
        $this->assertEquals(0, $bernard->heures_abs_non_justifiees);
    }
}
