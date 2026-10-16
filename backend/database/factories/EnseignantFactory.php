<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enseignant>
 */
class EnseignantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'specialite' => $this->faker->randomElement(['Mathématiques', 'Français', 'Histoire-Géographie', 'Anglais']),
            'telephone' => $this->faker->numerify('06 ## ## ## ##'),
            'user_id' => fn () => User::factory()->create()->assignRole(Role::Professeur->value)->id,
        ];
    }
}
