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
 * @property int $court_type_id
 * @property string $name
 * @property string $number
 * @property array $images
 * @property \Illuminate\Http\UploadedFile|null $images.*
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

class CreateCourtRequest extends FormRequest
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
            'court_type_id' => EasyHashAction::decode($this->court_type_id, 'court-type-id'),
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
            'court_type_id' => ['required', 'integer', Rule::exists('courts_type', 'id')],
            'name' => ['required', 'string', 'min:3', 'max:255', Rule::unique('courts', 'name')->where('tenant_id', $tenantId)],
            'number' => ['required', 'numeric', 'min:1', 'max:9999', Rule::unique('courts', 'number')->where('tenant_id', $tenantId)],
            'status' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'court_type_id.required' => 'O tipo de quadra é obrigatório',
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
            'images.array' => 'A(s) imagem(s) não estão válidas',
            'images.*.image' => 'A imagem não está válida',
            'images.*.mimes' => 'A imagem deve ser um arquivo de imagem (jpeg, png, jpg, gif, svg)',
            'images.*.max' => 'A imagem deve ter no máximo 10MB',
        ];
    }

    public function attributes(): array
    {
        return [
            'court_type_id' => 'Tipo de quadra',
            'name' => 'Nome',
            'number' => 'Número',
            'status' => 'Estado',
            'images' => 'Imagens',
            'images.*' => 'Imagem',
        ];
    }
}
