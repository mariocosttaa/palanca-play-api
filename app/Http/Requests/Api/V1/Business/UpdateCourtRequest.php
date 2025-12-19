<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validates
 *
 * Input fields:
 * @property int|null $tenant_id
 * @property Tenant|null $tenant
 * @property string $name
 * @property int $number
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

class UpdateCourtRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        $courtTypeId = $this->input('court_type_id');
        $this->merge([
            'id' => EasyHashAction::decode($this->route('court_id'), 'court-id'),
            'court_type_id' => $courtTypeId && is_string($courtTypeId)
                ? EasyHashAction::decode($courtTypeId, 'court-type-id')
                : null,
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
        $courtId = $this->input('id');

        return [
            'court_type_id' => ['nullable', 'integer', 'exists:courts_type,id'],
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('courts', 'name')->where('tenant_id', $tenantId)->ignore($courtId)],
            'number' => ['required', 'numeric', 'min:1', 'max:9999', Rule::unique('courts', 'number')->where('tenant_id', $tenantId)->ignore($courtId)],
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'court_type_id.integer' => 'O tipo de quadra deve ser um número inteiro',
            'court_type_id.exists' => 'O tipo de quadra não existe',
            'name.required' => 'O nome é obrigatório',
            'name.string' => 'O nome deve ser uma string',
            'name.min' => 'O nome deve ter pelo menos 3 caracteres',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'name.unique' => 'O nome já existe',
            'number.required' => 'O número é obrigatório',
            'number.numeric' => 'O número deve ser um número',
            'number.min' => 'O número deve ser pelo menos 1',
            'number.max' => 'O número deve ser no máximo 9999',
            'number.unique' => 'O número já existe',
            'status.boolean' => 'O status deve ser verdadeiro ou falso',
        ];
    }

    public function attributes(): array
    {
        return [
            'court_type_id' => 'Tipo de quadra',
            'name' => 'Nome',
            'number' => 'Número',
            'status' => 'Estado',
        ];
    }
}