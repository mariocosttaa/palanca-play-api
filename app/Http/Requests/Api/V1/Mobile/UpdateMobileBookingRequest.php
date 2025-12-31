<?php

namespace App\Http\Requests\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileBookingRequest extends FormRequest
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
        if ($this->route('booking_id')) {
            $this->merge([
                'booking_id' => EasyHashAction::decode($this->route('booking_id'), 'booking-id'),
            ]);
        }

        if ($this->input('court_id')) {
            $this->merge([
                'court_id' => EasyHashAction::decode($this->input('court_id'), 'court-id'),
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
            'court_id' => ['sometimes', 'integer', Rule::exists(Court::class, 'id')],
            'start_date' => 'sometimes|date|after_or_equal:today',
            'slots' => 'sometimes|array|min:1',
            'slots.*.start' => 'required_with:slots|date_format:H:i',
            'slots.*.end' => 'required_with:slots|date_format:H:i|after:slots.*.start',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any() && $this->has('slots')) {
                $this->validateSlotsContiguity($validator);
                $this->validateSlotsAvailability($validator);
            }
        });
    }

    /**
     * Validate that slots are contiguous (no gaps between them)
     */
    protected function validateSlotsContiguity($validator)
    {
        $slots = $this->input('slots');
        
        if (count($slots) > 1) {
            for ($i = 0; $i < count($slots) - 1; $i++) {
                $currentEnd = $slots[$i]['end'];
                $nextStart = $slots[$i + 1]['start'];
                
                if ($currentEnd !== $nextStart) {
                    $validator->errors()->add(
                        'slots',
                        'Os horários devem ser contíguos (sem intervalos entre eles)'
                    );
                    break;
                }
            }
        }
    }

    /**
     * Validate that all slots are available
     */
    protected function validateSlotsAvailability($validator)
    {
        $courtId = $this->input('court_id');
        $date = $this->input('start_date');
        $slots = $this->input('slots');

        // If court_id is not provided, we can't validate availability
        if (!$courtId || !$date) {
            return;
        }

        $court = \App\Models\Court::with('tenant')->find($courtId);
        
        if (!$court) {
            return;
        }

        // Get available slots for the date
        $availableSlots = $court->getAvailableSlots($date);
        
        // Check each requested slot
        foreach ($slots as $index => $requestedSlot) {
            $found = false;
            
            foreach ($availableSlots as $availableSlot) {
                if ($availableSlot['start'] === $requestedSlot['start'] && 
                    $availableSlot['end'] === $requestedSlot['end']) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $validator->errors()->add(
                    "slots.{$index}",
                    "O horário {$requestedSlot['start']} - {$requestedSlot['end']} não está disponível"
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'court_id.exists' => 'A quadra selecionada não existe.',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou uma data futura.',
            'slots.*.start.required_with' => 'A hora de início é obrigatória.',
            'slots.*.end.required_with' => 'A hora de término é obrigatória.',
            'slots.*.end.after' => 'A hora de término deve ser após a hora de início.',
        ];
    }
}


