<?php

namespace App\Http\Requests\Personnes;

use App\Models\Eleve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportElevesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Eleve::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', File::types(['xlsx', 'xls', 'csv'])->max('5mb')],
        ];
    }
}
