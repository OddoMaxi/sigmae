<?php

namespace App\Http\Requests\Reception;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmerLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sécurité assurée par : middleware permission:lots.receive + vérification HMAC token + vérification ambassade
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Confirmations individuelles
            'confirmations'                    => 'required|array|min:1|max:500',
            'confirmations.*.passeport_id'     => 'required|integer|exists:passeports,id',
            'confirmations.*.statut'           => 'required|in:confirme,anomalie,manquant',
            'confirmations.*.note'             => 'nullable|string|max:500',
            'confirmations.*.type_anomalie'    => 'nullable|in:manquant,endommage,errone,autre',

            // Option : enchaîner directement en DISPONIBLE_RETRAIT + envoyer email
            'aller_disponible_retrait' => 'boolean',

            // Commentaire global de l'ambassade pour cette réception
            'commentaire'              => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'confirmations.required'                => 'La liste des confirmations est obligatoire.',
            'confirmations.min'                     => 'Au moins une confirmation est requise.',
            'confirmations.max'                     => 'Maximum 500 confirmations par requête.',
            'confirmations.*.passeport_id.required' => "L'identifiant du passeport est obligatoire.",
            'confirmations.*.passeport_id.exists'   => 'Un passeport référencé est introuvable.',
            'confirmations.*.statut.required'       => 'Le statut de confirmation est obligatoire.',
            'confirmations.*.statut.in'             => 'Le statut doit être "confirme", "anomalie" ou "manquant".',
            'confirmations.*.type_anomalie.in'      => 'Type invalide : manquant, endommage, errone, autre.',
        ];
    }
}
