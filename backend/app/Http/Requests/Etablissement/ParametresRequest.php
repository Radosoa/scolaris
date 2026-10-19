<?php

namespace App\Http\Requests\Etablissement;

use App\Enums\Permission;
use App\Support\Devise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Modification des réglages de l'établissement, réservée à la direction. */
class ParametresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::StructureGerer->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'devise' => ['required', 'string', Rule::in(Devise::codes())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'devise.required' => 'Choisissez une devise.',
            'devise.in' => 'Cette devise n\'est pas proposée.',
        ];
    }
}
