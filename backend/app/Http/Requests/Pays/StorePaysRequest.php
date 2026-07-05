<?php

namespace App\Http\Requests\Pays;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $paysId = $this->route('pays')?->id;

        return [
            'code_iso' => ['required', 'string', 'size:2', 'alpha', Rule::unique('pays', 'code_iso')->ignore($paysId)],
            'nom'      => 'required|string|max:100',
            'capitale' => 'nullable|string|max:100',
            'is_active'=> 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code_iso.required' => 'Le code ISO 2 lettres est obligatoire.',
            'code_iso.size'     => 'Le code ISO doit contenir exactement 2 caractères.',
            'code_iso.alpha'    => 'Le code ISO doit contenir uniquement des lettres.',
            'code_iso.unique'   => 'Ce code ISO est déjà utilisé.',
            'nom.required'      => 'Le nom du pays est obligatoire.',
        ];
    }
}
