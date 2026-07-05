<?php

namespace App\Http\Requests\Ambassade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmbassadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'code'          => ['sometimes', 'string', 'max:10', Rule::unique('ambassades')->ignore($this->route('ambassade'))],
            'nom'           => 'sometimes|string|max:200',
            'pays'          => 'sometimes|string|max:100',
            'ville'         => 'sometimes|string|max:100',
            'email_contact' => 'nullable|email|max:255',
            'responsable'   => 'nullable|string|max:150',
            'is_active'     => 'sometimes|boolean',
        ];
    }
}
