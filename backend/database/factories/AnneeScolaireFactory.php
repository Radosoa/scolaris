<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeScolaire>
 */
class AnneeScolaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debut = $this->faker->unique()->numberBetween(2000, 2090);

        return [
            'libelle' => $debut.'-'.($debut + 1),
            'date_debut' => "{$debut}-09-01",
            'date_fin' => ($debut + 1).'-07-15',
        ];
    }

    /** Année qui commence en septembre de l'année donnée. */
    public function debutant(int $annee): static
    {
        return $this->state([
            'libelle' => $annee.'-'.($annee + 1),
            'date_debut' => "{$annee}-09-01",
            'date_fin' => ($annee + 1).'-07-15',
        ]);
    }

    public function active(): static
    {
        return $this->afterCreating(fn (AnneeScolaire $annee) => $annee->forceFill(['active' => true])->save());
    }
}
