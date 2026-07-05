<?php

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

class SignalerAnomalieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type'        => 'required|in:manquant,endommage,errone,autre',
            'description' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'Le type d\'anomalie est obligatoire.',
            'type.in'              => 'Type invalide. Valeurs acceptées : manquant, endommage, errone, autre.',
            'description.required' => 'La description de l\'anomalie est obligatoire.',
        ];
    }
}
