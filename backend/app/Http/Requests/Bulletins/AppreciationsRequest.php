<?php

namespace App\Http\Requests\Bulletins;

use App\Models\Bulletin;
use Illuminate\Foundation\Http\FormRequest;

/** Appréciations d'une matière en lot (une par élève). */
class AppreciationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('saisirAppreciations', [Bulletin::class, $this->route('affectation')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
            'appreciations' => ['required', 'array', 'max:200'],
            'appreciations.*.ligne_id' => ['required', 'integer', 'distinct'],
            'appreciations.*.appreciation' => ['present', 'nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'appreciations.*.appreciation.max' => 'L\'appréciation ne doit pas dépasser 300 caractères.',
        ];
    }

    /** Une appréciation vide est enregistrée comme absente. */
    protected function prepareForValidation(): void
    {
        $appreciations = $this->input('appreciations');
        if (! is_array($appreciations)) {
            return;
        }

        $this->merge(['appreciations' => array_map(function ($saisie) {
            if (is_array($saisie) && is_string($saisie['appreciation'] ?? null)) {
                $texte = trim($saisie['appreciation']);
                $saisie['appreciation'] = $texte === '' ? null : $texte;
            }

            return $saisie;
        }, $appreciations)]);
    }
}
