<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Actions\General\EasyHashAction;
use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $name
 * @property string|null $surname
 * @property string $email
 * @property string $password
 * @property string|null $country_id
 * @property string|null $calling_code
 * @property string|null $phone
 * @property string|null $timezone
 * @property string|null $device_name
 */
class UserRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim(strtolower($this->email ?? '')),
            'name' => trim($this->name ?? ''),
            'surname' => $this->surname ? trim($this->surname) : null,
            'calling_code' => $this->calling_code ? trim($this->calling_code) : null,
            'phone' => $this->phone ? trim($this->phone) : null,
            'timezone' => $this->timezone ? trim($this->timezone) : null,
        ]);

        // Decode hashed country_id if provided
        if ($this->country_id) {
            $this->merge([
                'country_id' => EasyHashAction::decode($this->country_id, 'country-id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(\App\Models\User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'calling_code' => ['nullable', 'string', 'max:10', Rule::exists(Country::class, 'calling_code'), 'required_with:phone'],
            'phone' => ['nullable', 'integer', 'digits_between:4,20', 'required_with:calling_code'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}

