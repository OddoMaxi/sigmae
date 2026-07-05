<?php

namespace App\Http\Requests\Transporteur;

use App\Models\Transporteur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransporteurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('transporteurs.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'nom'            => 'required|string|max:200|unique:transporteurs,nom',
            'type'           => ['nullable', Rule::in(Transporteur::TYPES)],
            'contact'        => 'nullable|string|max:150',
            'telephone'      => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255|unique:transporteurs,email',
            'adresse'        => 'nullable|string|max:500',
            'pays_desservis' => 'nullable|array',
            'pays_desservis.*' => 'string|max:10',
            'lien_suivi'     => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'       => 'Le nom du transporteur est obligatoire.',
            'nom.unique'         => 'Un transporteur avec ce nom existe déjà.',
            'email.unique'       => 'Cette adresse email est déjà utilisée par un autre transporteur.',
            'type.in'            => 'Type invalide. Valeurs acceptées : ' . implode(', ', Transporteur::TYPES) . '.',
            'lien_suivi.url'     => 'Le lien de suivi doit être une URL valide.',
            'pays_desservis.array' => 'Les pays desservis doivent être une liste.',
        ];
    }
}
