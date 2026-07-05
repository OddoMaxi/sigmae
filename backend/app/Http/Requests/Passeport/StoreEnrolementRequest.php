<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrolementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'prenom_titulaire' => ['required', 'string', 'max:100'],
            'nom_titulaire'    => ['required', 'string', 'max:100'],
            'date_naissance'   => ['required', 'date', 'before:today'],
            'email_citoyen'    => ['nullable', 'email', 'max:200'],
            'telephone'        => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'prenom_titulaire.required' => 'Le prénom est obligatoire.',
            'nom_titulaire.required'    => 'Le nom est obligatoire.',
            'date_naissance.required'   => 'La date de naissance est obligatoire.',
            'date_naissance.before'     => 'La date de naissance doit être dans le passé.',
            'email_citoyen.email'       => 'L\'adresse email n\'est pas valide.',
        ];
    }
}
