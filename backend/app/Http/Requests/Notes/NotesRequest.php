<?php

namespace App\Http\Requests\Notes;

use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;

/** Enregistrement en lot des notes d'une évaluation (grille de saisie). */
class NotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evaluation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Evaluation $evaluation */
        $evaluation = $this->route('evaluation');

        return [
            'notes' => ['required', 'array', 'max:200'],
            'notes.*.eleve_id' => ['required', 'integer', 'distinct'],
            'notes.*.absent' => ['sometimes', 'boolean'],
            'notes.*.valeur' => ['present', 'nullable', 'numeric', 'min:0', 'max:'.$evaluation->bareme, 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var Evaluation $evaluation */
        $evaluation = $this->route('evaluation');

        return [
            'notes.*.valeur.max' => 'La note ne peut pas dépasser le barème ('.rtrim(rtrim(number_format($evaluation->bareme, 2, ',', ''), '0'), ',').').',
            'notes.*.valeur.min' => 'La note ne peut pas être négative.',
            'notes.*.valeur.numeric' => 'La note doit être un nombre.',
            'notes.*.valeur.decimal' => 'Deux décimales au maximum.',
        ];
    }
}
