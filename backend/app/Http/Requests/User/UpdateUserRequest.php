<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'         => 'sometimes|string|min:2|max:150',
            'email'        => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password'     => 'sometimes|string|min:8|max:255',
            'role'         => ['sometimes', Rule::exists('roles', 'name')],
            'ambassade_id' => 'nullable|exists:ambassades,id',
            'is_active'    => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'        => 'Cette adresse email est déjà utilisée.',
            'password.min'        => 'Le mot de passe doit comporter au moins 8 caractères.',
            'role.in'             => 'Le rôle sélectionné n\'est pas valide.',
            'ambassade_id.exists' => 'L\'ambassade sélectionnée n\'existe pas.',
        ];
    }
}
