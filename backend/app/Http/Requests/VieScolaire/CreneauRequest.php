<?php

namespace App\Http\Requests\VieScolaire;

use App\Models\Creneau;
use Illuminate\Foundation\Http\FormRequest;

/** Création ou modification d'un créneau d'emploi du temps (PUT complet). */
class CreneauRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Creneau::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'affectation_id' => ['required', 'integer', 'exists:affectations,id'],
            'jour' => ['required', 'integer', 'between:1,6'],
            'heure_debut' => ['required', 'date_format:H:i', 'after_or_equal:07:00'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut', 'before_or_equal:19:00'],
            'salle' => ['present', 'nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'affectation_id.required' => 'Choisissez la matière et le professeur.',
            'jour.between' => 'Choisissez un jour du lundi au samedi.',
            'heure_debut.after_or_equal' => 'Les cours commencent au plus tôt à 07:00.',
            'heure_fin.after' => 'L\'heure de fin doit suivre l\'heure de début.',
            'heure_fin.before_or_equal' => 'Les cours finissent au plus tard à 19:00.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['salle' => $this->input('salle')]);
    }
}
