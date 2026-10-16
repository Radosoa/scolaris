<?php

namespace App\Http\Requests\Finances;

use App\Models\Frais;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ligne de frais : niveau + année + libellé + montant à la création ;
 * en modification, seuls le libellé et le montant changent.
 */
class FraisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Frais::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Frais|null $frais */
        $frais = $this->route('frais');

        return [
            'niveau_id' => [Rule::requiredIf(! $frais), 'integer', 'exists:niveaux,id'],
            'annee_scolaire_id' => [Rule::requiredIf(! $frais), 'integer', 'exists:annees_scolaires,id'],
            'libelle' => [
                'required', 'string', 'max:100',
                Rule::unique('frais')
                    ->where('niveau_id', $frais?->niveau_id ?? $this->input('niveau_id'))
                    ->where('annee_scolaire_id', $frais?->annee_scolaire_id ?? $this->input('annee_scolaire_id'))
                    ->ignore($frais?->id),
            ],
            'montant' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'libelle.unique' => 'Ce niveau a déjà une ligne de frais portant ce libellé.',
            'montant.min' => 'Le montant doit être positif.',
            'montant.decimal' => 'Deux décimales au maximum.',
        ];
    }
}
