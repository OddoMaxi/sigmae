<?php

namespace App\Http\Requests\Passeport;

use Illuminate\Foundation\Http\FormRequest;

class StorePasseportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.create') ?? false;
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
        return [
            'numero'                  => ['required', 'string', 'regex:' . self::NUMERO_REGEX, 'unique:passeports,numero'],
            'reference_demande'       => 'nullable|string|max:50|unique:passeports,reference_demande',
            'nom_titulaire'           => ['required', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'prenom_titulaire'        => ['required', 'string', 'min:2', 'max:100', 'regex:' . self::NAME_REGEX],
            'date_naissance'          => 'nullable|date|before:today',
            'email_citoyen'           => 'nullable|email|max:200',
            'telephone'               => 'nullable|string|max:30',
            'pays_destination_id'     => 'nullable|integer|exists:pays,id',
            'ambassade_destination_id'=> 'nullable|integer|exists:ambassades,id',
            'date_impression'         => 'nullable|date|before_or_equal:today',
            'date_reception_mae'      => 'nullable|date|before_or_equal:today',
            // Statut initial : imprime (défaut depuis imprimerie) ou recu_mae si déjà reçu
            'statut'                  => 'nullable|in:imprime,recu_mae,en_stock',
        ];
    }

    public function messages(): array
    {
        return [
            'numero.required'           => 'Le numéro de passeport est obligatoire.',
            'numero.regex'              => 'Le numéro de passeport doit contenir entre 6 et 15 lettres/chiffres, sans espace ni caractère spécial.',
            'numero.unique'             => 'Ce numéro de passeport existe déjà dans le système.',
            'reference_demande.unique'  => 'Cette référence de demande est déjà enregistrée.',
            'nom_titulaire.required'    => 'Le nom du titulaire est obligatoire.',
            'nom_titulaire.regex'       => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'prenom_titulaire.required' => 'Le prénom du titulaire est obligatoire.',
            'prenom_titulaire.regex'    => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'date_naissance.before'     => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_impression.before_or_equal'    => 'La date d\'impression ne peut pas être dans le futur.',
            'date_reception_mae.before_or_equal' => 'La date de réception MAE ne peut pas être dans le futur.',
            'pays_destination_id.exists'          => 'Le pays de destination sélectionné est invalide.',
            'ambassade_destination_id.exists'     => 'L\'ambassade de destination sélectionnée est invalide.',
        ];
    }
}
