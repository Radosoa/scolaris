<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function utilisateur(Role $role, array $attributs = []): User
    {
        return User::factory()->create(['password' => 'secret123', ...$attributs])->assignRole($role->value);
    }

    public function test_un_utilisateur_peut_se_connecter_et_recevoir_un_token(): void
    {
        $user = $this->utilisateur(Role::Professeur);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'roles', 'permissions']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.roles', ['professeur']);

        $this->assertNotNull($user->fresh()->derniere_connexion_at);
        $this->assertCount(1, $user->tokens);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse_avec_un_message_en_francais(): void
    {
        $user = $this->utilisateur(Role::Admin);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('auth.failed'));
    }

    public function test_les_champs_obligatoires_sont_valides_en_francais(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('errors.password.0', 'Le champ mot de passe est obligatoire.');
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = $this->utilisateur(Role::Secretariat, ['actif' => false]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertCount(0, $user->tokens);
    }

    public function test_la_connexion_est_limitee_a_cinq_tentatives_par_minute(): void
    {
        $user = $this->utilisateur(Role::Admin);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertTooManyRequests();
    }

    public function test_me_renvoie_l_utilisateur_avec_ses_roles_et_permissions(): void
    {
        $user = $this->utilisateur(Role::Professeur);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['professeur'])
            ->assertJsonPath('data.permissions', ['absences.saisir', 'appreciations.saisir', 'notes.saisir']);
    }

    public function test_me_sans_token_renvoie_401_en_francais(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Non authentifié.']);
    }

    public function test_la_deconnexion_revoque_uniquement_le_token_courant(): void
    {
        $user = $this->utilisateur(Role::Parent);
        $token = $user->createToken('navigateur')->plainTextToken;
        $user->createToken('mobile');

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertSame('mobile', $user->fresh()->tokens->first()->name);
    }

    public function test_le_middleware_de_role_bloque_les_autres_roles(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'role:admin'])
            ->get('/api/test-admin', fn () => ['ok' => true]);

        $admin = $this->utilisateur(Role::Admin);
        $prof = $this->utilisateur(Role::Professeur);

        $this->actingAs($admin, 'sanctum')->getJson('/api/test-admin')->assertOk();
        $this->actingAs($prof, 'sanctum')->getJson('/api/test-admin')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Action non autorisée.']);
    }

    public function test_l_admin_possede_toutes_les_permissions(): void
    {
        $admin = $this->utilisateur(Role::Admin);

        foreach (\App\Enums\Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }
    }
}
