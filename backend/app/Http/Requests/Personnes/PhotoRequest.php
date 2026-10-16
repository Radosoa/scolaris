<?php

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('eleve'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
        ];
    }
}
