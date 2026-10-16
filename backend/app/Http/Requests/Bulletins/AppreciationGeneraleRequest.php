<?php

namespace App\Http\Requests\Bulletins;

use Illuminate\Foundation\Http\FormRequest;

/** Appréciation générale de la direction sur un bulletin. */
class AppreciationGeneraleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bulletin'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'appreciation_generale' => ['present', 'nullable', 'string', 'max:600'],
        ];
    }

    /** Une appréciation vide efface l'appréciation existante. */
    protected function prepareForValidation(): void
    {
        $texte = trim((string) $this->input('appreciation_generale'));
        $this->merge(['appreciation_generale' => $texte === '' ? null : $texte]);
    }
}
