<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Country;

/**
 * Request validates
 *
 * Input fields:
 * @property string $name
 * @property string|null $surname
 * @property string|null $email
 * @property string|null $phone
 * @property int|null $country_id
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
class UpdateClientRequest extends FormRequest
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
        $mergeData = [
            'id' => EasyHashAction::decode($this->route('client_id'), 'user-id'),
        ];

        if ($this->has('email')) {
            $mergeData['email'] = $this->email ? trim(strtolower($this->email)) : null;
        }
        if ($this->has('name')) {
            $mergeData['name'] = trim($this->name ?? '');
        }
        if ($this->has('surname')) {
            $mergeData['surname'] = $this->surname ? trim($this->surname) : null;
        }
        if ($this->has('calling_code')) {
            $mergeData['calling_code'] = $this->calling_code ? trim($this->calling_code) : null;
        }
        if ($this->has('phone')) {
            $mergeData['phone'] = $this->phone ? trim($this->phone) : null;
        }

        $this->merge($mergeData);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clientId = $this->input('id');

        return [
            'name' => 'sometimes|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', Rule::unique(User::class)->ignore($clientId)],
            'calling_code' => ['nullable', 'string', 'max:10', Rule::exists(Country::class, 'calling_code'), 'required_with:phone'],
            'phone' => ['nullable', 'integer', 'digits_between:4,20', 'required_with:calling_code'],
            'country_id' => ['nullable', Rule::exists(Country::class, 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'O nome deve ser uma string',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'surname.string' => 'O sobrenome deve ser uma string',
            'surname.max' => 'O sobrenome deve ter no máximo 255 caracteres',
            'email.email' => 'O email deve ser válido',
            'email.unique' => 'Este email já está em uso',
            'calling_code.exists' => 'O código de país não é válido',
            'calling_code.required_with' => 'O código de país é obrigatório quando o telefone é informado',
            'phone.integer' => 'O telefone deve conter apenas números',
            'phone.digits_between' => 'O telefone deve ter entre 4 e 20 dígitos',
            'phone.required_with' => 'O telefone é obrigatório quando o código de país é informado',
            'country_id.exists' => 'O país selecionado não existe',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nome',
            'surname' => 'Sobrenome',
            'email' => 'Email',
            'calling_code' => 'Código do País',
            'phone' => 'Telefone',
            'country_id' => 'País',
        ];
    }
}
