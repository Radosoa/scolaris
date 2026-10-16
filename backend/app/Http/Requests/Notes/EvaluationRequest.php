<?php

namespace App\Http\Requests\Notes;

use App\Enums\TypeEvaluation;
use App\Models\Affectation;
use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Evaluation|null $evaluation */
        $evaluation = $this->route('evaluation');
        if ($evaluation) {
            return $this->user()->can('update', $evaluation);
        }

        // Affectation inexistante : laisser la validation répondre 422
        $affectation = Affectation::find($this->integer('affectation_id'));

        return $affectation === null || $this->user()->can('create', [Evaluation::class, $affectation]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // L'affectation d'une évaluation existante ne change pas
            'affectation_id' => $this->route('evaluation') ? ['prohibited'] : ['required', 'integer', 'exists:affectations,id'],
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
            'type' => ['required', Rule::enum(TypeEvaluation::class)],
            'titre' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'bareme' => ['required', 'numeric', 'min:1', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'affectation_id' => 'affectation',
            'periode_id' => 'période',
            'bareme' => 'barème',
        ];
    }
}
