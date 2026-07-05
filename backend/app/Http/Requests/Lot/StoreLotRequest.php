<?php

namespace App\Http\Requests\Lot;

use App\Models\Transporteur;
use Illuminate\Foundation\Http\FormRequest;

class StoreLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('lots.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'ambassade_id'          => 'required|exists:ambassades,id',
            'transporteur_id'       => [
                'required',
                'exists:transporteurs,id',
                function ($attribute, $value, $fail) {
                    $t = Transporteur::find($value);
                    if ($t && ! $t->is_active) {
                        $fail("Le transporteur «{$t->nom}» est inactif et ne peut pas être utilisé.");
                    }
                },
            ],
            'reference_suivi'       => 'nullable|string|max:150',
            'date_expedition'       => 'nullable|date|after_or_equal:today',
            'date_reception_prevue' => 'nullable|date|after:date_expedition',
            'notes'                 => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'ambassade_id.required'          => "L'ambassade de destination est obligatoire.",
            'ambassade_id.exists'            => "L'ambassade sélectionnée n'existe pas.",
            'transporteur_id.required'       => "Le transporteur est obligatoire pour l'expédition.",
            'transporteur_id.exists'         => "Le transporteur sélectionné n'existe pas.",
            'date_expedition.after_or_equal' => "La date d'expédition ne peut pas être dans le passé.",
            'date_reception_prevue.after'    => "La date de réception prévue doit être après la date d'expédition.",
        ];
    }
}
