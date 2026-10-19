<?php

namespace App\Http\Requests\Etablissement;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/** Identité de l'école (en-tête des bulletins et reçus), modifiable par la direction. */
class IdentiteRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'chef_etablissement' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nom' => 'nom de l’établissement', 'chef_etablissement' => 'chef d’établissement', 'telephone' => 'téléphone'];
    }
}
