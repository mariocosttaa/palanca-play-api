<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $email
 * @property string $code
 */
class VerifyEmailUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
