<?php

namespace App\Http\Requests\ReceptionMAE;

use Illuminate\Foundation\Http\FormRequest;

class ValiderReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'identifiant'        => 'required|string|max:50',
            // Requis si le dossier est encore à l'état enrolee (numéro pas encore assigné)
            'numero'             => 'nullable|string|max:50|unique:passeports,numero',
            'date_reception_mae' => 'nullable|date|before_or_equal:today',
            'notes'              => 'nullable|string|max:500',
            'aller_en_stock'     => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'identifiant.required'               => 'Le numéro ou la référence du passeport est obligatoire.',
            'numero.unique'                       => 'Ce numéro de passeport existe déjà dans le système.',
            'date_reception_mae.before_or_equal'  => 'La date de réception ne peut pas être dans le futur.',
        ];
    }
}
