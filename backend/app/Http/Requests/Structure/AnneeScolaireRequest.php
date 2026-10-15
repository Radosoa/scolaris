<?php

namespace App\Http\Requests\Structure;

use App\Models\AnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnneeScolaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annee = $this->route('annee');

        return $annee
            ? $this->user()->can('update', $annee)
            : $this->user()->can('create', AnneeScolaire::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creation = $this->route('annee') === null;

        return [
            'libelle' => [
                'required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('annees_scolaires')->ignore($this->route('annee')),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'active' => $creation ? ['boolean'] : ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // « 2025-2026 » : la seconde année suit immédiatement la première
                if ($validator->errors()->has('libelle') || ! $this->filled('libelle')) {
                    return;
                }
                [$debut, $fin] = array_map('intval', explode('-', $this->input('libelle')));
                if ($fin !== $debut + 1) {
                    $validator->errors()->add('libelle', 'Le libellé doit couvrir deux années consécutives (ex. 2025-2026).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.regex' => 'Le libellé doit être au format AAAA-AAAA (ex. 2025-2026).',
            'active.prohibited' => 'Utilisez l\'action « Activer » pour changer l\'année active.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'libelle' => 'libellé',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
        ];
    }
}
