<?php

namespace App\Http\Requests;

use App\Enums\CibleAnnonce;
use App\Models\Annonce;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création ou modification (PUT complet) d'une annonce ciblée. */
class AnnonceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $annonce = $this->route('annonce');

        return $annonce
            ? $this->user()->can('update', $annonce)
            : $this->user()->can('create', Annonce::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150'],
            'contenu' => ['required', 'string', 'max:5000'],
            'cible' => ['required', Rule::enum(CibleAnnonce::class)],
            'classe_id' => ['nullable', 'required_if:cible,classe', 'integer', 'exists:classes,id'],
            // Vide : publication immédiate ; date future : publication programmée
            'date_publication' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'contenu.required' => 'Le message est obligatoire.',
            'cible.enum' => 'Choisissez à qui s\'adresse l\'annonce.',
            'classe_id.required_if' => 'Choisissez la classe concernée.',
        ];
    }
}
