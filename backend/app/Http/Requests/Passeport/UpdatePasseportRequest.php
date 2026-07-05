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

    public function rules(): array
    {
        $passeportId = $this->route('passeport')?->id;

        return [
            'reference_demande'       => ['sometimes', 'nullable', 'string', 'max:50',
                                          Rule::unique('passeports', 'reference_demande')->ignore($passeportId)],
            'nom_titulaire'           => 'sometimes|string|max:100',
            'prenom_titulaire'        => 'sometimes|string|max:100',
            'date_naissance'          => 'nullable|date|before:today',
            'email_citoyen'           => 'nullable|email|max:200',
            'telephone'               => 'nullable|string|max:30',
            'pays_destination_id'     => 'nullable|integer|exists:pays,id',
            'ambassade_destination_id'=> 'nullable|integer|exists:ambassades,id',
            'date_impression'         => 'nullable|date|before_or_equal:today',
            'date_reception_mae'      => 'nullable|date|before_or_equal:today',
        ];
    }
}
