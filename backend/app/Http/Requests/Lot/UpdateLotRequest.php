<?php

namespace App\Http\Requests\Lot;

use App\Models\Transporteur;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('lots.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'ambassade_id'          => 'sometimes|exists:ambassades,id',
            'reference_suivi'       => 'nullable|string|max:150',
            'transporteur_id'       => [
                'sometimes',
                'exists:transporteurs,id',
                function ($attribute, $value, $fail) {
                    $t = Transporteur::find($value);
                    if ($t && ! $t->is_active) {
                        $fail("Le transporteur «{$t->nom}» est inactif et ne peut pas être utilisé.");
                    }
                },
            ],
            'date_expedition'       => 'nullable|date',
            'date_reception_prevue' => 'nullable|date',
            'notes'                 => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'ambassade_id.exists'    => "L'ambassade sélectionnée n'existe pas.",
            'transporteur_id.exists' => "Le transporteur sélectionné n'existe pas.",
        ];
    }
}
