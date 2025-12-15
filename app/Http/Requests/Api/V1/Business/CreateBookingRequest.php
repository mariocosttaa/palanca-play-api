<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
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
        if ($this->input('court_id')) {
            $this->merge([
                'court_id' => EasyHashAction::decode($this->input('court_id'), 'court-id'),
            ]);
        }
        
        if ($this->input('client_id')) {
            $this->merge([
                'client_id' => EasyHashAction::decode($this->input('client_id'), 'user-id'),
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
            'court_id' => 'required|exists:courts,id',
            'client_id' => 'nullable|exists:users,id',
            'client' => 'required_without:client_id|array',
            'client.name' => 'required_with:client|string|max:255',
            'client.email' => 'nullable|email|max:255|unique:users,email',
            'client.phone' => 'nullable|string|max:20',
            'client.calling_code' => 'nullable|string|max:5',
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'nullable|integer|min:0',
            'paid_at_venue' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'O cliente selecionado não existe.',
            'client.required_without' => 'É necessário selecionar um cliente ou criar um novo.',
            'client.name.required_with' => 'O nome do cliente é obrigatório.',
            'client.email.email' => 'O email do cliente deve ser válido.',
            'client.email.unique' => 'Este email já está em uso.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou uma data futura.',
            'start_time.required' => 'A hora de início é obrigatória.',
            'end_time.required' => 'A hora de término é obrigatória.',
            'end_time.after' => 'A hora de término deve ser após a hora de início.',
        ];
    }
}
