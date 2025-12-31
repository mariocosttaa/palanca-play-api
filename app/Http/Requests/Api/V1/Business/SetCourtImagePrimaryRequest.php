<?php

namespace App\Http\Requests\Api\V1\Business;

use Illuminate\Foundation\Http\FormRequest;

class SetCourtImagePrimaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    public function prepareForValidation()
    {
        // Convert string booleans to actual booleans
        if ($this->has('is_primary')) {
            $isPrimary = $this->input('is_primary');
            if (is_string($isPrimary)) {
                $this->merge([
                    'is_primary' => filter_var($isPrimary, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            }
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
            'is_primary' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'is_primary.required' => 'O campo is_primary é obrigatório',
            'is_primary.boolean' => 'O campo is_primary deve ser verdadeiro ou falso',
        ];
    }
}






