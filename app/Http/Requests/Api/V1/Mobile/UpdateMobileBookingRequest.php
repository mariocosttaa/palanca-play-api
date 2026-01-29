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

        // Handle Timezone Conversion for Partial Updates
        if ($this->has(['start_date']) || $this->has(['slots'])) {
            $bookingId = $this->input('booking_id');
            $booking = \App\Models\Booking::find($bookingId);

            if ($booking) {
                $timezoneService = app(\App\Services\TimezoneService::class);

                // 1. Get current booking start time in User Timezone
                // Stored as UTC: 2025-01-01 17:00:00
                // User TZ (NY): 2025-01-01 12:00:00
                $currentStartUtc = \Carbon\Carbon::parse($booking->start_date->format('Y-m-d') . ' ' . $booking->start_time->format('H:i:s'), 'UTC');
                $currentUserTime = $currentStartUtc->copy()->setTimezone($timezoneService->getContextTimezone());

                // 2. Determine New User Date
                $newUserDate = $this->input('start_date') ?? $currentUserTime->format('Y-m-d');

                // 3. Determine New User Slots
                $slots = $this->input('slots');
                
                // If slots are not provided, we need to infer them from existing booking time
                // But existing booking might not match "slots" structure if it wasn't created via mobile or if interval changed.
                // However, if we are only updating date, we want to keep the same "Time".
                if (!$slots) {
                    $slots = [[
                        'start' => $currentUserTime->format('H:i'),
                        'end' => $currentStartUtc->copy()->addMinutes($currentStartUtc->diffInMinutes(\Carbon\Carbon::parse($booking->end_date->format('Y-m-d') . ' ' . $booking->end_time->format('H:i:s'), 'UTC')))->setTimezone($timezoneService->getContextTimezone())->format('H:i')
                    ]];
                }

                // 4. Convert New User Date + Slots to UTC
                $result = $timezoneService->convertSlotsToUtc($newUserDate, $slots);

                if (!empty($result['slots'])) {
                    $this->merge([
                        'slots' => $result['slots'],
                        'start_date' => $result['start_date'] ?? $newUserDate,
                    ]);
                }
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
        $date = $this->input('start_date'); // UTC
        $slots = $this->input('slots'); // UTC
        $bookingId = $this->input('booking_id');

        // If court_id is not provided, fetch from booking
        if (!$courtId && $bookingId) {
            $booking = \App\Models\Booking::find($bookingId);
            $courtId = $booking?->court_id;
        }

        if (!$courtId || !$date) {
            return;
        }

        $court = \App\Models\Court::with('tenant')->find($courtId);
        
        if (!$court) {
            return;
        }

        // Get authenticated user ID to allow sequential bookings (ignoring buffers)
        $userId = $this->user()?->id;

        // Check each requested slot using checkAvailability
        foreach ($slots as $index => $requestedSlot) {
            $error = $court->checkAvailability(
                $date,
                $requestedSlot['start'],
                $requestedSlot['end'],
                $userId, // excludeUserId
                $bookingId // excludeBookingId (to allow updating same booking)
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
            'court_id.exists' => 'A quadra selecionada não existe.',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou uma data futura.',
            'slots.*.start.required_with' => 'A hora de início é obrigatória.',
            'slots.*.end.required_with' => 'A hora de término é obrigatória.',
            'slots.*.end.after' => 'A hora de término deve ser após a hora de início.',
        ];
    }
}


