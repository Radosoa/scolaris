<?php

namespace App\Http\Requests\Personnes;

use App\Models\Affectation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $affectation = $this->route('affectation');

        return $affectation
            ? $this->user()->can('update', $affectation)
            : $this->user()->can('create', Affectation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enseignant_id' => ['required', 'integer', Rule::exists('enseignants', 'id')->whereNull('deleted_at')],
            'matiere_id' => [
                'required', 'integer', 'exists:matieres,id',
                Rule::unique('affectations')->where('classe_id', $this->input('classe_id'))->ignore($this->route('affectation')),
            ],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['matiere_id.unique' => 'Un enseignant est déjà affecté à cette matière dans cette classe.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['enseignant_id' => 'enseignant', 'matiere_id' => 'matière', 'classe_id' => 'classe'];
    }
}
