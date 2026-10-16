<?php

namespace App\Http\Requests\Personnes;

use App\Enums\LienParente;
use App\Enums\Sexe;
use App\Http\Requests\Concerns\ChampsFacultatifsNuls;
use App\Models\Eleve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EleveRequest extends FormRequest
{
    use ChampsFacultatifsNuls;

    /** @var list<string> */
    protected array $champsFacultatifs = ['lieu_naissance'];

    public function authorize(): bool
    {
        $eleve = $this->route('eleve');

        return $eleve
            ? $this->user()->can('update', $eleve)
            : $this->user()->can('create', Eleve::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'lieu_naissance' => ['nullable', 'string', 'max:100'],
            // Absent = liens inchangés ; tableau vide = aucun parent
            'tuteurs' => ['sometimes', 'array', 'max:6'],
            'tuteurs.*.tuteur_id' => ['required', 'integer', 'distinct', Rule::exists('tuteurs', 'id')->whereNull('deleted_at')],
            'tuteurs.*.lien' => ['required', Rule::enum(LienParente::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prenom' => 'prénom',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'tuteurs.*.tuteur_id' => 'parent',
            'tuteurs.*.lien' => 'lien de parenté',
        ];
    }
}
