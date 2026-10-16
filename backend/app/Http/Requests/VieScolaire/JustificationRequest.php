<?php

namespace App\Http\Requests\VieScolaire;

use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;

/** Justification (ou retrait de justification) d'une absence ou d'un retard. */
class JustificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('justifier', Absence::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'justifiee' => ['required', 'boolean'],
            'motif' => ['nullable', 'required_if:justifiee,true', 'string', 'max:255'],
            'toute_la_journee' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motif.required_if' => 'Indiquez le motif de l\'absence.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $motif = trim((string) $this->input('motif'));
        $this->merge(['motif' => $motif === '' ? null : $motif]);
    }
}
