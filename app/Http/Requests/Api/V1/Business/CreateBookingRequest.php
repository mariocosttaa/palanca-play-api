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

        // Handle slots input
        if ($this->has('slots') && is_array($this->input('slots'))) {
            $slots = $this->input('slots');
            if (count($slots) > 0) {
                // Take start from the first slot
                $firstSlot = $slots[0];
                // Take end from the last slot
                $lastSlot = $slots[count($slots) - 1];

                if (isset($firstSlot['start']) && isset($lastSlot['end'])) {
                    // Update request with extracted times, but keep slots for price calculation in controller if needed
                    $this->merge([
                        'start_time' => $firstSlot['start'],
                        'end_time'   => $lastSlot['end'],
                        // If start date is provided in request use it, otherwise we can't infer it easily from H:i slots alone 
                        // without context, but normally start_date is passed separately.
                    ]);
                }
            }
        }

        // Convert dates to UTC
        if ($this->has(['start_date', 'start_time', 'end_time'])) {
            $timezoneService = app(\App\Services\TimezoneService::class);
            
            // Handle slots conversion if present
            if ($this->has('slots') && is_array($this->input('slots'))) {
                $conversionResult = $timezoneService->convertSlotsToUtc(
                    $this->input('start_date'), 
                    $this->input('slots')
                );
                
                if (!empty($conversionResult['slots'])) {
                    $this->merge([
                        'slots' => $conversionResult['slots'],
                        // Update the base start_date/time/end_time from the converted slots 
                        // as they are more accurate (they might have shifted the date)
                        'start_date' => $conversionResult['start_date'],
                        'start_time' => $conversionResult['slots'][0]['start'],
                        'end_time' => $conversionResult['slots'][count($conversionResult['slots']) - 1]['end'],
                    ]);
                }
            } else {
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
                    ]);
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @bodyParam slots array Os horários a serem reservados (opcional se start_time/end_time fornecidos). Se forem enviados horários não contíguos (com intervalos), o sistema criará múltiplos agendamentos separados automaticamente.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'court_id' => 'required|exists:courts,id',
            'client_id' => 'required|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'slots' => 'nullable|array|min:1',
            'start_time' => 'required_without:slots|date_format:H:i',
            'end_time' => 'required_without:slots|date_format:H:i|after:start_time',
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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate slots if provided
            if (!$validator->errors()->any() && $this->has('slots')) {
                $this->validateSlotsAvailability($validator);
            }
        });
    }

    /**
     * Validate that all slots are available
     */
    protected function validateSlotsAvailability($validator)
    {
        $courtId = $this->input('court_id');
        $date = $this->input('start_date');
        $slots = $this->input('slots');
        $userId = $this->input('client_id');

        if (!$courtId || !$date) {
            return;
        }

        $court = \App\Models\Court::with('tenant')->find($courtId);
        
        if (!$court) {
            return;
        }

        // Check each requested slot using checkAvailability
        foreach ($slots as $index => $requestedSlot) {
            $error = $court->checkAvailability(
                $date,
                $requestedSlot['start'],
                $requestedSlot['end'],
                $userId // excludeUserId to allow sequential bookings for same user
            );
            
            if ($error) {
                $validator->errors()->add(
                    "slots.{$index}",
                    $error
                );
            }
        }
    }
}
