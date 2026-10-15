<?php

namespace App\Http\Requests\Structure;

use App\Models\Niveau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NiveauRequest extends FormRequest
{
    public function authorize(): bool
    {
        $niveau = $this->route('niveau');

        return $niveau
            ? $this->user()->can('update', $niveau)
            : $this->user()->can('create', Niveau::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $niveau = $this->route('niveau');

        return [
            'libelle' => ['required', 'string', 'max:50', Rule::unique('niveaux')->ignore($niveau)],
            'ordre' => ['required', 'integer', 'between:1,99', Rule::unique('niveaux')->ignore($niveau)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['libelle' => 'libellé'];
    }
}
