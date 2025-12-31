<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\CourtTypeEnum;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int|null $id
 * @property int|null $tenant_id
 * @property Tenant|null $tenant
 * @method void merge(array $data)
 * @method mixed route(string $key = null, mixed $default = null)
 * @method mixed input(string $key = null, mixed $default = null)
 */
class UpdateTenantRequest extends FormRequest
{

/**
 * Request validates
 *
 * @property string $country_id Example: Xy7z (hashed country ID)
 * @property string $name Example: Palanca Club
 * @property string $address Example: Luanda, Angola
 * @property decimal $latitude Example: -8.839988
 * @property decimal $longitude Example: 13.289437
 * @property bool $auto_confirm_bookings Example: true
 * @property int $booking_interval_minutes Example: 60
 * @property int $buffer_between_bookings_minutes Example: 15
 * @property string $currency Example: aoa
 * @property string $timezone Example: Africa/Luanda
 * @property string $timezone_id Example: Abc1 (hashed timezone ID)
 */
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        $this->merge([
            'tenant_id' => EasyHashAction::decode($this->route('tenant_id'), 'tenant-id'),
        ]);

        if ($this->timezone_id) {
            $this->merge([
                'timezone_id' => EasyHashAction::decode($this->timezone_id, 'timezone-id'),
            ]);
        }

        if ($this->country_id) {
            $this->merge([
                'country_id' => EasyHashAction::decode($this->country_id, 'country-id'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', Rule::exists(\App\Models\Country::class, 'id')],
            'name' => ['required', 'string', 'min:3', 'max:255',
                Rule::unique('tenants', 'name')->where('id', $this->tenant_id)->ignore($this->tenant_id, 'id')
            ],

            'address' => 'required|string|max:512',
            'latitude' => 'nullable|decimal:7',
            'longitude' => 'nullable|decimal:7',
            'currency' => 'required|in:usd,aoa,eur,brl',
            'timezone_id' => ['required', 'integer', Rule::exists(\App\Models\Timezone::class, 'id')],
            'auto_confirm_bookings' => 'required|boolean',
            'booking_interval_minutes' => 'required|integer|min:5|max:120',
            'buffer_between_bookings_minutes' => 'required|integer|min:0|max:120',
        ];
    }

    public function messages(): array
    {
        return [
            'country_id.required' => 'O país é obrigatório',
            'country_id.exists' => 'O país selecionado não é válido',
            'name.required' => 'O nome é obrigatório',
            'name.string' => 'O nome deve ser uma string',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'name.unique' => 'O nome já existe',
            'logo.image' => 'O logo deve ser uma imagem',
            'logo.max' => 'O logo deve ter no máximo 2MB',
            'address.string' => 'O endereço deve ser uma string',
            'address.max' => 'O endereço deve ter no máximo 512 caracteres',
            'latitude.decimal' => 'A latitude deve ser um número decimal',
            'longitude.decimal' => 'A longitude deve ser um número decimal',
            'currency.required' => 'A moeda é obrigatória',
            'currency.in' => 'A moeda selecionada não é válida',
            'timezone_id.required' => 'O fuso horário é obrigatório',
            'timezone_id.integer' => 'O fuso horário deve ser um número inteiro',
            'timezone_id.exists' => 'O fuso horário selecionado não é válido',
            'auto_confirm_bookings.required' => 'A confirmação de agendamento automática é obrigatória',
            'auto_confirm_bookings.boolean' => 'A confirmação de agendamento automática deve ser um booleano',
            'booking_interval_minutes.required' => 'O intervalo de tempo é obrigatório',
            'booking_interval_minutes.integer' => 'O intervalo de tempo deve ser um número inteiro',
            'booking_interval_minutes.min' => 'O intervalo de tempo deve ser pelo menos 5 minutos',
            'booking_interval_minutes.max' => 'O intervalo de tempo deve ser no máximo 120 minutos',
            'buffer_between_bookings_minutes.required' => 'O buffer entre agendamentos é obrigatório',
            'buffer_between_bookings_minutes.integer' => 'O buffer entre agendamentos deve ser um número inteiro',
            'buffer_between_bookings_minutes.max' => 'O buffer entre agendamentos deve ser no máximo 120 minutos',
        ];
    }

    public function attributes(): array
    {
        return [
            'country_id' => 'País',
            'name' => 'Nome',
            'logo' => 'Logo',
            'address' => 'Endereço',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'currency' => 'Moeda',
            'timezone_id' => 'Fuso horário',
            'auto_confirm_bookings' => 'Confirmação de agendamento automática',
            'booking_interval_minutes' => 'Intervalo de tempo',
            'buffer_between_bookings_minutes' => 'Buffer entre agendamentos',
        ];
    }
}
