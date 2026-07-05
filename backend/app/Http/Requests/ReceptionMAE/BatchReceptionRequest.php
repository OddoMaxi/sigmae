<?php

namespace App\Http\Requests\ReceptionMAE;

use Illuminate\Foundation\Http\FormRequest;

class BatchReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('passeports.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'passeports'                     => 'required|array|min:1|max:200',
            'passeports.*.identifiant'       => 'required|string|max:50',
            'passeports.*.date_reception_mae'=> 'nullable|date|before_or_equal:today',
            'passeports.*.notes'             => 'nullable|string|max:300',

            // Date commune (appliquée si non spécifiée par passeport)
            'date_reception_mae' => 'nullable|date|before_or_equal:today',
            // true = IMPRIMÉ→EN_STOCK, false = IMPRIMÉ→REÇU_MAE
            'aller_en_stock'     => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'passeports.required'          => 'La liste des passeports est obligatoire.',
            'passeports.min'               => 'Au moins un passeport est requis.',
            'passeports.max'               => 'Maximum 200 passeports par requête.',
            'passeports.*.identifiant.required' => 'Chaque passeport doit avoir un numéro ou une référence.',
        ];
    }
}
