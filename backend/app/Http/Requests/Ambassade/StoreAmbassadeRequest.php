<?php

namespace App\Http\Requests\Ambassade;

use Illuminate\Foundation\Http\FormRequest;

class StoreAmbassadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code'          => 'required|string|max:10|unique:ambassades,code',
            'nom'           => 'required|string|max:200',
            'pays'          => 'required|string|max:100',
            'ville'         => 'required|string|max:100',
            'email_contact' => 'nullable|email|max:255',
            'responsable'   => 'nullable|string|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'  => 'Ce code d\'ambassade est déjà utilisé.',
            'code.required'=> 'Le code est obligatoire.',
            'nom.required' => 'Le nom est obligatoire.',
            'pays.required'=> 'Le pays est obligatoire.',
            'ville.required'=> 'La ville est obligatoire.',
        ];
    }
}
