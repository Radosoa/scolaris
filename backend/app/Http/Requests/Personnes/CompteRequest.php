<?php

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Création du compte de connexion d'un élève ou d'un parent. */
class CompteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profil = $this->route('eleve') ?? $this->route('tuteur');

        return $this->user()->can('update', $profil);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'mot de passe'];
    }
}
