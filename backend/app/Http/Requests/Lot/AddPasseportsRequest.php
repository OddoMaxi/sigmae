<?php

namespace App\Http\Requests\Lot;

use Illuminate\Foundation\Http\FormRequest;

class AddPasseportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('lots.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'passeport_ids'   => 'required|array|min:1|max:500',
            'passeport_ids.*' => 'integer|exists:passeports,id',
        ];
    }

    public function messages(): array
    {
        return [
            'passeport_ids.required' => 'Vous devez sélectionner au moins un passeport.',
            'passeport_ids.min'      => 'Vous devez sélectionner au moins un passeport.',
            'passeport_ids.max'      => 'Vous ne pouvez pas ajouter plus de 500 passeports à la fois.',
            'passeport_ids.*.exists' => "Un ou plusieurs passeports sélectionnés n'existent pas.",
        ];
    }
}
