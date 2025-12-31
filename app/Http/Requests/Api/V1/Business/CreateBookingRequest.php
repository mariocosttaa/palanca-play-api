<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        // Convert dates to UTC
        if ($this->has(['start_date', 'start_time', 'end_time'])) {
            $timezoneService = app(\App\Services\TimezoneService::class);
            
            // Parse start datetime in user timezone
            $startString = $this->input('start_date') . ' ' . $this->input('start_time');
            $startUtc = $timezoneService->toUTC($startString);

            // Parse end datetime in user timezone
            // Assuming end time is on the same day as start time (as per current validation rules)
            $endString = $this->input('start_date') . ' ' . $this->input('end_time');
            $endUtc = $timezoneService->toUTC($endString);

            if ($startUtc && $endUtc) {
                $this->merge([
                    'start_date' => $startUtc->format('Y-m-d'),
                    'start_time' => $startUtc->format('H:i'),
                    'end_time' => $endUtc->format('H:i'),
                    // We might need to handle end_date if we support it, but for now
                    // we are limited by the request structure.
                    // If the conversion pushes it to the next day, start_date will update.
                    // If it crosses midnight in UTC, end_time will be < start_time, which will fail validation.
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
            'court_id' => 'required|exists:courts,id',
            'client_id' => 'required|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price' => 'nullable|integer|min:0',
            'status' => ['nullable', Rule::enum(BookingStatusEnum::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatusEnum::class)],
            'payment_method' => [
                'nullable',
                Rule::enum(PaymentMethodEnum::class),
                Rule::requiredIf(fn () => $this->payment_status === PaymentStatusEnum::PAID->value)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'É necessário selecionar um cliente.',
            'client_id.exists' => 'O cliente selecionado não existe.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou uma data futura.',
            'start_time.required' => 'A hora de início é obrigatória.',
            'end_time.required' => 'A hora de término é obrigatória.',
            'end_time.after' => 'A hora de término deve ser após a hora de início.',
        ];
    }
}
