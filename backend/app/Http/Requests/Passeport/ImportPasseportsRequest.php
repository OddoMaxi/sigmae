<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class ImportPasseportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isGestionnaire() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier d\'import est obligatoire.',
            'file.mimes'    => 'Le fichier doit être au format Excel (xlsx, xls) ou CSV.',
            'file.max'      => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
