<?php

namespace App\Http\Requests\Finances;

use App\Models\Frais;
use Illuminate\Foundation\Http\FormRequest;

/** Échéancier complet de l'année (remplace l'existant). */
class EcheancesRequest extends FormRequest
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
        return [
            'echeances' => ['present', 'array', 'max:12'],
            'echeances.*.libelle' => ['required', 'string', 'max:60'],
            'echeances.*.date_limite' => ['required', 'date_format:Y-m-d', 'distinct'],
            'echeances.*.pourcentage' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'echeances.*.date_limite.distinct' => 'Deux tranches ne peuvent pas avoir la même date limite.',
            'echeances.*.pourcentage.min' => 'Chaque tranche doit représenter une part positive.',
        ];
    }
}
