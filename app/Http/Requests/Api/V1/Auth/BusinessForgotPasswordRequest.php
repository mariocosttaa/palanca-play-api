<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $email
 */
class BusinessForgotPasswordRequest extends FormRequest
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
                Rule::exists(\App\Models\BusinessUser::class, 'email'),
            ],
        ];
    }
}
