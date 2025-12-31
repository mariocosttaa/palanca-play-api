<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $email
 */
class UpdateEmailRequest extends FormRequest
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
        $user = $this->user();
        $table = $user instanceof \App\Models\BusinessUser ? 'business_users' : 'users';

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique($table, 'email')->ignore($user->id),
            ],
        ];
    }
}
