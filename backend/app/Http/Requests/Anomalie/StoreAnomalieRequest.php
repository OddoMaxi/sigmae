<?php

namespace App\Http\Requests\Anomalie;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnomalieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type'         => 'required|in:manquant,endommage,errone,autre',
            'passeport_id' => 'nullable|exists:passeports,id',
            'lot_id'       => 'nullable|exists:lots,id',
            'description'  => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'Le type d\'anomalie est obligatoire.',
            'type.in'              => 'Type invalide.',
            'passeport_id.exists'  => 'Le passeport référencé n\'existe pas.',
            'lot_id.exists'        => 'Le lot référencé n\'existe pas.',
            'description.required' => 'La description de l\'anomalie est obligatoire.',
        ];
    }
}
