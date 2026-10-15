<?php

namespace App\Http\Requests\Structure;

use App\Models\Periode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $periode = $this->route('periode');

        return $periode
            ? $this->user()->can('update', $periode)
            : $this->user()->can('create', Periode::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Periode|null $periode */
        $periode = $this->route('periode');
        $anneeId = $periode?->annee_scolaire_id ?? $this->input('annee_scolaire_id');

        return [
            // L'année d'une période existante ne change pas
            'annee_scolaire_id' => $periode ? ['prohibited'] : ['required', 'integer', 'exists:annees_scolaires,id'],
            'libelle' => ['required', 'string', 'max:50'],
            'ordre' => [
                'required', 'integer', 'between:1,12',
                Rule::unique('periodes')->where('annee_scolaire_id', $anneeId)->ignore($periode),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordre.unique' => 'Une période porte déjà ce numéro d\'ordre pour cette année.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'annee_scolaire_id' => 'année scolaire',
            'libelle' => 'libellé',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
        ];
    }
}
