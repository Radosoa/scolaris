<?php

namespace App\Http\Requests\Structure;

use App\Models\Coefficient;
use Illuminate\Foundation\Http\FormRequest;

class CoefficientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Coefficient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coefficients' => ['required', 'array'],
            'coefficients.*.matiere_id' => ['required', 'integer', 'distinct', 'exists:matieres,id'],
            'coefficients.*.valeur' => ['present', 'nullable', 'numeric', 'min:0.5', 'max:20', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'coefficients.*.matiere_id' => 'matière',
            'coefficients.*.valeur' => 'coefficient',
        ];
    }
}
