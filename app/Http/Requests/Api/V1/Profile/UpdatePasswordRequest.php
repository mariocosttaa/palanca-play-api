<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @property string $current_password
 * @property string $new_password
 * @property string $new_password_confirmation
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(8),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.different' => 'A nova senha não pode ser igual à senha atual.',
        ];
    }
}
