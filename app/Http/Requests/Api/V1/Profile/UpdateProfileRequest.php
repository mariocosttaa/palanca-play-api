<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

use App\Actions\General\EasyHashAction;
use App\Models\Country;
use App\Models\Timezone;
use Illuminate\Validation\Rule;

/**
 * @property string $name
 * @property string $surname
 * @property string $phone_code
 * @property string $phone
 * @property string $timezone_id
 * @property string $country_id
 * @property string $locale
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->phone_code) {
            $this->merge([
                'phone_code' => str_replace('+', '', $this->phone_code),
            ]);
        }

        if ($this->country_id) {
            $this->merge([
                'country_id' => EasyHashAction::decode($this->country_id, 'country-id'),
            ]);
        }

        if ($this->timezone_id) {
            $this->merge([
                'timezone_id' => EasyHashAction::decode($this->timezone_id, 'timezone-id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'surname' => 'sometimes|string|max:255',
            'phone_code' => 'sometimes|string|max:5',
            'phone' => 'sometimes|string|max:20',
            'country_id' => ['sometimes', 'integer', Rule::exists(Country::class, 'id')],
            'timezone_id' => ['sometimes', 'integer', Rule::exists(Timezone::class, 'id')],
            'locale' => 'sometimes|in:en,pt,es,fr',
        ];
    }
}
