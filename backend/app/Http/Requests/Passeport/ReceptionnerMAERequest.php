<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class ReceptionnerMAERequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_reception_mae' => 'nullable|date|before_or_equal:today',
            'notes'              => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'date_reception_mae.before_or_equal' => 'La date de réception ne peut pas être dans le futur.',
        ];
    }
}
