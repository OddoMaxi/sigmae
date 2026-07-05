<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|min:2|max:150',
            'email'        => 'required|email|max:255|unique:users,email',
            'password'     => 'required|string|min:8|max:255',
            'role'         => ['required', Rule::exists('roles', 'name')],
            'ambassade_id' => 'nullable|exists:ambassades,id',
            'is_active'    => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'Le nom complet est obligatoire.',
            'email.unique'          => 'Cette adresse email est déjà utilisée.',
            'password.min'          => 'Le mot de passe doit comporter au moins 8 caractères.',
            'role.in'               => 'Le rôle sélectionné n\'est pas valide.',
            'ambassade_id.exists'   => 'L\'ambassade sélectionnée n\'existe pas.',
        ];
    }
}
