<?php

namespace App\Http\Requests\Structure;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classe = $this->route('classe');

        return $classe
            ? $this->user()->can('update', $classe)
            : $this->user()->can('create', Classe::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Classe|null $classe */
        $classe = $this->route('classe');
        $anneeId = $this->input('annee_scolaire_id', $classe?->annee_scolaire_id);

        return [
            'niveau_id' => ['required', 'integer', 'exists:niveaux,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annees_scolaires,id'],
            'nom' => [
                'required', 'string', 'max:50',
                Rule::unique('classes')->where('annee_scolaire_id', $anneeId)->ignore($classe),
            ],
            'capacite' => ['required', 'integer', 'between:1,200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.unique' => 'Une classe porte déjà ce nom pour cette année scolaire.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'niveau_id' => 'niveau',
            'annee_scolaire_id' => 'année scolaire',
            'capacite' => 'capacité',
        ];
    }
}
