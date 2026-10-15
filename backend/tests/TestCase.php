<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Avec RefreshDatabase, les rôles et permissions sont créés avant chaque test. */
    protected bool $seed = true;

    protected string $seeder = RolePermissionSeeder::class;

    /** Crée un utilisateur avec ce rôle et l'authentifie via Sanctum. */
    protected function connecter(Role $role): User
    {
        $user = User::factory()->create()->assignRole($role->value);
        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
