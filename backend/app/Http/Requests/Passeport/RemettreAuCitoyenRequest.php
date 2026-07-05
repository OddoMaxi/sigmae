<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class RemettreAuCitoyenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Agent réception ou utilisateur ambassade avec permission lots.receive
        return auth()->user()?->hasPermission('lots.receive') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_remise' => 'nullable|date|before_or_equal:today',
            'notes'       => 'nullable|string|max:500',
        ];
    }
}
