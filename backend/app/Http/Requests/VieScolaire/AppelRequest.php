<?php

namespace App\Http\Requests\VieScolaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Enregistrement de l'appel d'un créneau à une date. */
class AppelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('faireAppel', $this->route('creneau'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'eleves' => ['present', 'array', 'max:100'],
            'eleves.*.eleve_id' => ['required', 'integer', 'distinct'],
            'eleves.*.statut' => ['required', Rule::in(['present', 'absent', 'retard'])],
            'eleves.*.minutes_retard' => ['nullable', 'required_if:eleves.*.statut,retard', 'integer', 'min:1', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'eleves.*.statut.in' => 'Statut inconnu : présent, absent ou retard.',
            'eleves.*.minutes_retard.required_if' => 'Indiquez la durée du retard.',
            'eleves.*.minutes_retard.min' => 'Le retard est d\'au moins une minute.',
        ];
    }
}
