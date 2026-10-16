<?php

namespace App\Http\Requests\Personnes;

use App\Http\Requests\Concerns\ChampsFacultatifsNuls;
use App\Models\Enseignant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EnseignantRequest extends FormRequest
{
    use ChampsFacultatifsNuls;

    /** @var list<string> */
    protected array $champsFacultatifs = ['specialite', 'telephone'];

    public function authorize(): bool
    {
        $enseignant = $this->route('enseignant');

        return $enseignant
            ? $this->user()->can('update', $enseignant)
            : $this->user()->can('create', Enseignant::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Enseignant|null $enseignant */
        $enseignant = $this->route('enseignant');

        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'specialite' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]{6,30}$/'],
            // Identifiant de connexion de l'enseignant
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($enseignant?->user_id)],
            // Mot de passe initial, à la création uniquement
            'password' => $enseignant ? ['prohibited'] : ['required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone n\'est pas valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['prenom' => 'prénom', 'specialite' => 'spécialité', 'telephone' => 'téléphone', 'password' => 'mot de passe'];
    }
}
