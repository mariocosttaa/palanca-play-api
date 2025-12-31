<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $email
 * @property string $code
 * @property string $password
 * @property string $password_confirmation
 */
class UserVerifyPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim(strtolower($this->email ?? '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::exists(\App\Models\User::class, 'email'),
            ],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
