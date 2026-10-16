<?php

namespace App\Http\Requests\Personnes;

use App\Enums\StatutInscription;
use App\Models\Inscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création : eleve_id + classe_id (+ date). Modification : classe_id et/ou statut.
 */
class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inscription = $this->route('inscription');

        return $inscription
            ? $this->user()->can('update', $inscription)
            : $this->user()->can('create', Inscription::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->route('inscription')) {
            return [
                'classe_id' => ['required_without:statut', 'integer', 'exists:classes,id'],
                'statut' => ['required_without:classe_id', Rule::enum(StatutInscription::class)],
            ];
        }

        return [
            'eleve_id' => ['required', 'integer', Rule::exists('eleves', 'id')->whereNull('deleted_at')],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'date_inscription' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['eleve_id' => 'élève', 'classe_id' => 'classe', 'date_inscription' => 'date d\'inscription'];
    }
}
