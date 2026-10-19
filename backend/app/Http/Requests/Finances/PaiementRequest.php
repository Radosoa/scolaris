<?php

namespace App\Http\Requests\Finances;

use App\Enums\ModePaiement;
use App\Models\Paiement;
use App\Services\ParametresService;
use App\Support\Devise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Encaissement d'une tranche (montant libre, plafonné au reste à payer par le service). */
class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Paiement::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'min:0.01', 'decimal:0,'.$this->devise()->decimales],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'mode' => ['required', Rule::enum(ModePaiement::class)],
            'reference' => [Rule::requiredIf(fn () => ModePaiement::tryFrom((string) $this->input('mode'))?->exigeReference() ?? false), 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'montant.min' => 'Le montant doit être positif.',
            'montant.decimal' => $this->devise()->decimales === 0 ? 'Montant sans décimales attendu.' : 'Deux décimales au maximum.',
            'date.before_or_equal' => 'La date du paiement ne peut pas être dans le futur.',
            'mode.enum' => 'Mode de paiement inconnu.',
            'reference.required' => 'Indiquez la référence (n° de chèque, de virement ou de transaction).',
        ];
    }

    private function devise(): Devise
    {
        return app(ParametresService::class)->devise();
    }
}
