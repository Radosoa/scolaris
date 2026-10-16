<?php

namespace Database\Factories;

use App\Models\Eleve;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Eleve>
 */
class EleveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sexe = $this->faker->randomElement(['M', 'F']);

        return [
            'matricule' => $this->faker->unique()->numerify('T###-####'),
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName($sexe === 'M' ? 'male' : 'female'),
            'sexe' => $sexe,
            'date_naissance' => $this->faker->dateTimeBetween('-15 years', '-11 years')->format('Y-m-d'),
            'lieu_naissance' => $this->faker->city(),
        ];
    }
}
