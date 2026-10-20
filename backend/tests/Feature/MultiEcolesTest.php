<?php

namespace Tests\Feature;

use App\Models\Ecole;
use App\Services\Ecoles\GestionEcoles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Une base par école : deux écoles (alpha, beta) créées dans des bases de test dédiées.
 * Chaque requête désigne son école par l'en-tête X-Ecole.
 */
class MultiEcolesTest extends TestCase
{
    private const BASES = ['scolaris_test_central', 'scolaris_test_500001', 'scolaris_test_500002'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecoles.multi' => true,
            'ecoles.prefixe_base' => 'scolaris_test_',
            'database.connections.central.database' => 'scolaris_test_central',
        ]);
        $this->supprimerBases();
        DB::connection('mysql')->statement('CREATE DATABASE scolaris_test_central');
        Artisan::call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true]);

        $gestion = app(GestionEcoles::class);
        $gestion->creer('alpha', 'Lycée Alpha', 'direction@alpha.mg', 'motdepasse-alpha');
        $gestion->creer('beta', 'Collège Beta', 'direction@beta.mg', 'motdepasse-beta');
    }

    protected function tearDown(): void
    {
        $this->supprimerBases();
        parent::tearDown();
    }

    public function test_chaque_ecole_a_ses_comptes_et_ses_donnees(): void
    {
        // Bases numérotées dans l'ordre de création
        $this->assertSame(['scolaris_test_500001', 'scolaris_test_500002'], Ecole::orderBy('code')->pluck('base_de_donnees')->all());

        // Le compte d'alpha n'existe pas dans beta
        $jeton = $this->seConnecter('alpha', 'direction@alpha.mg', 'motdepasse-alpha');
        $this->ecole('beta')->postJson('/api/auth/login', ['email' => 'direction@alpha.mg', 'password' => 'motdepasse-alpha'])
            ->assertUnprocessable();

        // Un niveau créé dans alpha n'apparaît pas dans beta
        $this->ecole('alpha')->withToken($jeton)->postJson('/api/niveaux', ['libelle' => 'Terminale', 'ordre' => 7])->assertCreated();
        $this->ecole('alpha')->withToken($jeton)->getJson('/api/niveaux')->assertJsonPath('data.0.libelle', 'Terminale');

        $this->app['auth']->forgetGuards();
        $jetonBeta = $this->seConnecter('beta', 'direction@beta.mg', 'motdepasse-beta');
        $this->ecole('beta')->withToken($jetonBeta)->getJson('/api/niveaux')->assertJsonCount(0, 'data');

        // Un jeton d'alpha présenté à beta n'ouvre rien
        $this->app['auth']->forgetGuards();
        $this->ecole('beta')->withToken($jeton)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_l_ecole_doit_etre_designee_et_active(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'direction@alpha.mg', 'password' => 'motdepasse-alpha'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ecole' => 'Indiquez le code de votre établissement.']);
        $this->ecole('gamma')->getJson('/api/ecole')
            ->assertJsonValidationErrors(['ecole' => 'Aucun établissement ne correspond à ce code.']);

        Ecole::where('code', 'beta')->update(['actif' => false]);
        $this->ecole('beta')->getJson('/api/ecole')->assertForbidden();
    }

    public function test_la_direction_modifie_l_identite_de_son_ecole(): void
    {
        $this->ecole('alpha')->getJson('/api/ecole')
            ->assertOk()
            ->assertJsonPath('data.code', 'alpha')
            ->assertJsonPath('data.nom', 'Lycée Alpha')
            ->assertJsonPath('data.adresse', null);

        $jeton = $this->seConnecter('alpha', 'direction@alpha.mg', 'motdepasse-alpha');
        $this->ecole('alpha')->withToken($jeton)->putJson('/api/ecole', [
            'nom' => 'Lycée Alpha Andohalo',
            'adresse' => 'Lot II A 45, Andohalo',
            'ville' => 'Antananarivo',
            'telephone' => '+261 34 00 000 00',
        ])->assertOk()->assertJsonPath('data.ville', 'Antananarivo');

        // Le nom est recopié dans la base centrale ; beta n'est pas touchée
        $this->assertSame('Lycée Alpha Andohalo', Ecole::where('code', 'alpha')->value('nom'));
        $this->ecole('beta')->getJson('/api/ecole')->assertJsonPath('data.nom', 'Collège Beta')->assertJsonPath('data.ville', null);
    }

    public function test_une_creation_interrompue_est_reprise_au_demarrage(): void
    {
        // Une école complète n'est pas touchée
        $this->artisan('ecole:creer', ['code' => 'alpha', 'nom' => 'Lycée Alpha', '--email' => 'x@alpha.mg', '--mot-de-passe' => 'secret-123', '--si-absente' => true])
            ->expectsOutput('École « alpha » déjà présente.');

        // Remplissage interrompu : l'école est supprimée puis recréée (même code, nouvelle base vide)
        Ecole::where('code', 'beta')->update(['prete_le' => null]);
        $this->artisan('ecole:creer', ['code' => 'beta', 'nom' => 'Collège Beta', '--email' => 'nouvelle@beta.mg', '--mot-de-passe' => 'secret-456', '--si-absente' => true])
            ->expectsOutput('Création de « beta » interrompue précédemment : elle est recréée.')
            ->assertSuccessful();

        $this->assertNotNull(Ecole::where('code', 'beta')->value('prete_le'));
        $this->seConnecter('beta', 'nouvelle@beta.mg', 'secret-456');
        $this->app['auth']->forgetGuards();
        $this->ecole('beta')->postJson('/api/auth/login', ['email' => 'direction@beta.mg', 'password' => 'motdepasse-beta'])->assertUnprocessable();
    }

    public function test_les_relances_passent_dans_chaque_ecole(): void
    {
        $this->artisan('scolaris:relancer-impayes')
            ->expectsOutput('[alpha] Aucun nouveau retard.')
            ->expectsOutput('[beta] Aucun nouveau retard.')
            ->assertSuccessful();
    }

    private function ecole(string $code): static
    {
        return $this->withHeader('X-Ecole', $code);
    }

    private function seConnecter(string $ecole, string $email, string $motDePasse): string
    {
        return $this->ecole($ecole)->postJson('/api/auth/login', ['email' => $email, 'password' => $motDePasse])->assertOk()->json('token');
    }

    private function supprimerBases(): void
    {
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'scolaris_test']);
        foreach (self::BASES as $base) {
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$base}`");
        }
    }
}
