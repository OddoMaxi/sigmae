<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrolementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** Lettres (accents inclus), espaces, apostrophes et tirets uniquement. */
    private const NAME_REGEX = '/^[\p{L} \'\-]+$/u';

    public function rules(): array
    {
        return [
            'prenom_titulaire' => ['required', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'nom_titulaire'    => ['required', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'date_naissance'   => ['required', 'date', 'before:today'],
            'email_citoyen'    => ['nullable', 'email', 'max:200'],
            'telephone'        => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'prenom_titulaire.required' => 'Le prénom est obligatoire.',
            'prenom_titulaire.regex'    => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'nom_titulaire.required'    => 'Le nom est obligatoire.',
            'nom_titulaire.regex'       => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'date_naissance.required'   => 'La date de naissance est obligatoire.',
            'date_naissance.before'     => 'La date de naissance doit être dans le passé.',
            'email_citoyen.email'       => 'L\'adresse email n\'est pas valide.',
        ];
    }
}
