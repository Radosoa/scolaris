<?php

namespace App\Http\Requests\Personnes;

use App\Http\Requests\Concerns\ChampsFacultatifsNuls;
use App\Models\Tuteur;
use Illuminate\Foundation\Http\FormRequest;

class TuteurRequest extends FormRequest
{
    use ChampsFacultatifsNuls;

    /** @var list<string> */
    protected array $champsFacultatifs = ['email'];

    public function authorize(): bool
    {
        $tuteur = $this->route('tuteur');

        return $tuteur
            ? $this->user()->can('update', $tuteur)
            : $this->user()->can('create', Tuteur::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['telephone.regex' => 'Le numéro de téléphone n\'est pas valide.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['prenom' => 'prénom', 'telephone' => 'téléphone'];
    }
}
