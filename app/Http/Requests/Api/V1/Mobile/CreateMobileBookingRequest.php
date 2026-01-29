<?php

namespace App\Http\Requests\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class CreateMobileBookingRequest extends FormRequest
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

        // Convert dates to UTC
        if ($this->has(['start_date', 'slots']) && is_array($this->input('slots'))) {
            $timezoneService = app(\App\Services\TimezoneService::class);
            $startDate = $this->input('start_date');
            $slots = $this->input('slots');

            // Find court to get tenant timezone as fallback
            $court = \App\Models\Court::with('tenant')->find($this->input('court_id'));
            $fallbackTz = $court?->tenant?->timezone;

            // TimezoneService will automatically prioritize user's timezone if set
            $result = $timezoneService->convertSlotsToUtc($startDate, $slots, $fallbackTz);

            if (!empty($result['slots'])) {
                $this->merge([
                    'slots' => $result['slots'],
                    'start_date' => $result['start_date'] ?? $startDate,
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
            'start_date' => 'required|date|after_or_equal:today',
            'slots' => 'required|array|min:1',
            'slots.*.start' => 'required|date_format:H:i',
            'slots.*.end' => 'required|date_format:H:i|after:slots.*.start',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
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
        $date = $this->input('start_date'); // This is now UTC date
        $slots = $this->input('slots'); // These are now UTC slots

        $court = \App\Models\Court::with('tenant')->find($courtId);
        
        if (!$court) {
            return;
        }

        // Check each requested slot using checkAvailability
        foreach ($slots as $index => $requestedSlot) {
            // Check each requested slot. We pass the user ID to allow sequential 
            // bookings by the same user (bypassing the maintenance buffer).
            $error = $court->checkAvailability(
                $date,
                $requestedSlot['start'],
                $requestedSlot['end'],
                $this->user()?->id
            );
            
            if ($error) {
                $validator->errors()->add(
                    "slots.{$index}",
                    $error
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'court_id.required' => 'A quadra é obrigatória.',
            'court_id.exists' => 'A quadra selecionada não existe.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou uma data futura.',
            'slots.required' => 'Pelo menos um horário deve ser selecionado.',
            'slots.*.start.required' => 'A hora de início é obrigatória.',
            'slots.*.end.required' => 'A hora de término é obrigatória.',
            'slots.*.end.after' => 'A hora de término deve ser após a hora de início.',
        ];
    }
}
