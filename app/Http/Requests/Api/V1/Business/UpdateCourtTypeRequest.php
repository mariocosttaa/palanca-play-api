<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int|null $id
 * @property int|null $tenant_id
 * @property Tenant|null $tenant
 *
 * @method void merge(array $data)
 * @method mixed route(string $key = null, mixed $default = null)
 * @method mixed input(string $key = null, mixed $default = null)
 */
class UpdateCourtTypeRequest extends FormRequest
{

/**
 * Request validates
 *
 * Input fields:
 * @property string $id
 * @property string $type
 * @property string $courtTypeId
 * @property string $name
 * @property string $description
 * @property int $interval_time_minutes
 * @property int $buffer_time_minutes
 * @property bool $status
 *
 * Magic/inherited methods (MANDATORY):
 * @method bool hasFile(string $key)
 * @method \Illuminate\Http\UploadedFile|null file(string $key)
 * @method mixed route(string $key = null)
 * @method bool boolean(string $key)
 * @method array all()
 * @method void merge(array $data)
 * @method array input(string $key = null, mixed $default = null)
 * @method string route(string $key = null)
 * @mixin \Illuminate\Http\Request
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
            'id' => EasyHashAction::decode($this->route('court_type_id'), 'court-type-id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenant?->id ?? $this->input('tenant_id');

        return [
            'type' => ['required', 'string', Rule::enum(CourtTypeEnum::class)],
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('courts_type', 'name')->where('tenant_id', $tenantId)->ignore($this->id)],
            'description' => 'nullable|string|max:255',
            'interval_time_minutes' => 'required|integer|min:5|max:120',
            'buffer_time_minutes' => 'required|integer|min:0|max:120',
            'price_per_interval' => 'required|integer|min:0',
            'status' => 'required|boolean|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo é obrigatório',
            'type.string' => 'O tipo deve ser uma string',
            'type.enum' => 'O tipo deve ser uma não é válido',
            'name.required' => 'O nome é obrigatório',
            'name.string' => 'O nome deve ser uma string',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'name.unique' => 'O nome já existe',
            'description.string' => 'A descrição deve ser uma string',
            'description.max' => 'A descrição deve ter no máximo 255 caracteres',
            'interval_time_minutes.required' => 'O intervalo de tempo é obrigatório',
            'interval_time_minutes.integer' => 'O intervalo de tempo deve ser um número inteiro',
            'interval_time_minutes.min' => 'O intervalo de tempo deve ser pelo menos 5 minutos',
            'interval_time_minutes.max' => 'O intervalo de tempo deve ser no máximo 120 minutos',
            'buffer_time_minutes.required' => 'O buffer de tempo é obrigatório',
            'buffer_time_minutes.integer' => 'O buffer de tempo deve ser um número inteiro',
            'buffer_time_minutes.min' => 'O buffer de tempo deve ser pelo menos 0 minutos',
            'buffer_time_minutes.max' => 'O buffer de tempo deve ser no máximo 120 minutos',
            'price_per_interval.required' => 'O preço por intervalo é obrigatório',
            'price_per_interval.integer' => 'O preço por intervalo deve ser um número inteiro',
            'price_per_interval.min' => 'O preço por intervalo deve ser pelo menos 0',
            'status.required' => 'O status é obrigatório',
            'status.boolean' => 'O status está incorreto',
            'status.in' => 'O status deve estar ativo ou inativo',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Tipo',
            'name' => 'Nome',
            'description' => 'Descrição',
            'interval_time_minutes' => 'Intervalo de tempo',
            'buffer_time_minutes' => 'Buffer de tempo',
            'price_per_interval' => 'Preço por intervalo',
            'status' => 'Estado',
        ];
    }
}
