<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePasseportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.update') ?? false;
    }

    /** Lettres (accents inclus), espaces, apostrophes et tirets uniquement. */
    private const NAME_REGEX = '/^[\p{L} \'\-]+$/u';

    /** Numéro de passeport : lettres majuscules et chiffres uniquement. */
    private const NUMERO_REGEX = '/^[A-Z0-9]{6,15}$/';

    protected function prepareForValidation(): void
    {
        if ($this->numero) {
            $this->merge(['numero' => strtoupper(trim($this->numero))]);
        }
    }

    public function rules(): array
    {
        $passeportId = $this->route('passeport')?->id;

        return [
            'numero'                  => ['sometimes', 'string', 'regex:' . self::NUMERO_REGEX,
                                          Rule::unique('passeports', 'numero')->ignore($passeportId)],
            'reference_demande'       => ['sometimes', 'nullable', 'string', 'max:50',
                                          Rule::unique('passeports', 'reference_demande')->ignore($passeportId)],
            'nom_titulaire'           => ['sometimes', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'prenom_titulaire'        => ['sometimes', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'date_naissance'          => 'nullable|date|before:today',
            'email_citoyen'           => 'nullable|email|max:200',
            'telephone'               => 'nullable|string|max:30',
            'pays_destination_id'     => 'nullable|integer|exists:pays,id',
            'ambassade_destination_id'=> 'nullable|integer|exists:ambassades,id',
            'date_impression'         => 'nullable|date|before_or_equal:today',
            'date_reception_mae'      => 'nullable|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'numero.regex'       => 'Le numéro de passeport doit contenir entre 6 et 15 lettres/chiffres, sans espace ni caractère spécial.',
            'numero.unique'      => 'Ce numéro de passeport existe déjà dans le système.',
            'nom_titulaire.regex'    => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'prenom_titulaire.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        ];
    }
}
