<?php

namespace App\Http\Requests\ReceptionMAE;

use Illuminate\Foundation\Http\FormRequest;

class ScannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'identifiant' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'identifiant.required' => 'Le numéro ou la référence du passeport est obligatoire.',
        ];
    }
}
