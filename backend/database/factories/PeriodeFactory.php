<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periode>
 */
class PeriodeFactory extends Factory
{
    /**
     * Par défaut : le premier trimestre de l'année.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'libelle' => 'Trimestre 1',
            'ordre' => 1,
            'date_debut' => fn (array $attributs) => AnneeScolaire::find($attributs['annee_scolaire_id'])->date_debut,
            'date_fin' => fn (array $attributs) => AnneeScolaire::find($attributs['annee_scolaire_id'])->date_debut->copy()->addMonths(3)->subDay(),
        ];
    }

    public function cloturee(): static
    {
        return $this->afterCreating(fn (Periode $periode) => $periode->forceFill(['cloturee' => true])->save());
    }
}
