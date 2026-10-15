<?php

namespace App\Http\Requests\Structure;

use App\Models\Matiere;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        $matiere = $this->route('matiere');

        return $matiere
            ? $this->user()->can('update', $matiere)
            : $this->user()->can('create', Matiere::class);
    }

    protected function prepareForValidation(): void
    {
        // Unicité vérifiée sur la forme stockée (majuscules)
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $matiere = $this->route('matiere');

        return [
            'nom' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('matieres')->ignore($matiere)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Le code ne peut contenir que des lettres, des chiffres et des tirets.',
        ];
    }
}
