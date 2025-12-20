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
        $this->merge([
            'id' => EasyHashAction::decode($this->route('court_id'), 'court-id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->tenant->id;
        $courtId = $this->input('id');

        return [
            'name'          => ['sometimes', 'required', 'string', 'min:3', 'max:255', Rule::unique('courts', 'name')->where('tenant_id', $tenantId)->ignore($courtId)],
            'number'        => ['sometimes', 'required', 'numeric', 'min:1', 'max:9999', Rule::unique('courts', 'number')->where('tenant_id', $tenantId)->ignore($courtId)],
            'status'        => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
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
            'name' => 'Nome',
            'number' => 'Número',
            'status' => 'Estado',
        ];
    }
}