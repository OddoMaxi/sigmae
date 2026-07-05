<?php

namespace App\Http\Requests\Anomalie;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnomalieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'statut'      => 'sometimes|in:ouvert,en_traitement,resolu',
            'description' => 'sometimes|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'statut.in' => 'Statut invalide. Valeurs acceptées : ouvert, en_traitement, resolu.',
        ];
    }
}
