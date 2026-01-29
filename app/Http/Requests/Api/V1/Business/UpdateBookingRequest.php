<?php
namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int|null $court_id
 * @property string|null $start_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int|null $price
 * @property bool|null $paid_at_venue
 * @property bool|null $is_paid
 * @property bool|null $is_cancelled
 * @property int $booking_id
 * @method mixed route(string $key = null)
 */
class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
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

        // Handle slots input
        if ($this->has('slots') && is_array($this->input('slots'))) {
            $slots = $this->input('slots');
            if (count($slots) > 0) {
                // Take start from the first slot
                $firstSlot = $slots[0];
                // Take end from the last slot
                $lastSlot = $slots[count($slots) - 1];

                if (isset($firstSlot['start']) && isset($lastSlot['end'])) {
                    // Update request with extracted times, but keep slots for price calculation in controller
                    $this->merge([
                        'start_time' => $firstSlot['start'],
                        'end_time'   => $lastSlot['end'],
                    ]);
                }
            }
        }

        // Handle Timezone Conversion
        if ($this->hasAny(['start_date', 'start_time', 'end_time'])) {
            $bookingId = $this->booking_id ?? EasyHashAction::decode($this->route('booking_id'), 'booking-id');
            $booking = Booking::find($bookingId);

            if ($booking) {
                $timezoneService = app(\App\Services\TimezoneService::class);

                // 1. Get current values in User TZ
                // Note: We assume the DB values are in UTC (which they will be after this feature is live)
                // If they are currently not UTC, this might shift them, but we are migrating.
                // Actually, for existing data, it might be messy if we don't migrate DB.
                // But let's assume we are starting fresh or data is compatible.
                
                // We need to construct the full datetime from DB to convert it correctly
                $currentStartUtc = \Carbon\Carbon::parse($booking->start_date->format('Y-m-d') . ' ' . $booking->start_time->format('H:i:s'), 'UTC');
                $currentEndUtc = \Carbon\Carbon::parse($booking->start_date->format('Y-m-d') . ' ' . $booking->end_time->format('H:i:s'), 'UTC');
                // Note: end_date is assumed same as start_date in DB currently.

                $currentStartUser = $timezoneService->toUserTime($currentStartUtc);
                $currentEndUser = $timezoneService->toUserTime($currentEndUtc);
                
                // Parse back to Carbon to extract date/time parts in User TZ
                $startUserCarbon = \Carbon\Carbon::parse($currentStartUser);
                $endUserCarbon = \Carbon\Carbon::parse($currentEndUser);

                // 2. Overlay Input Data
                $newStartDate = $this->input('start_date', $startUserCarbon->format('Y-m-d'));
                $newStartTime = $this->input('start_time', $startUserCarbon->format('H:i'));
                $newEndTime = $this->input('end_time', $endUserCarbon->format('H:i'));

                // 3. Convert back to UTC
                $newStartString = $newStartDate . ' ' . $newStartTime;
                // For end time, we use the NEW start date as base (User TZ assumption of single day)
                $newEndString = $newStartDate . ' ' . $newEndTime;

                $newStartUtc = $timezoneService->toUTC($newStartString);
                $newEndUtc = $timezoneService->toUTC($newEndString);

                if ($newStartUtc && $newEndUtc) {
                    $this->merge([
                        'start_date' => $newStartUtc->format('Y-m-d'),
                        'start_time' => $newStartUtc->format('H:i'),
                        'end_time' => $newEndUtc->format('H:i'),
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
            'court_id'       => ['sometimes', 'integer', Rule::exists(Court::class, 'id')],
            'start_date'     => 'sometimes|date|after_or_equal:today',
            'slots'          => 'sometimes|array|min:1',
            'start_time'     => 'sometimes|required_without:slots|date_format:H:i',
            'end_time'       => 'sometimes|required_without:slots|date_format:H:i|after:start_time',
            'price'          => 'sometimes|integer|min:0',
            'status'         => ['sometimes', Rule::enum(BookingStatusEnum::class)],
            'payment_status' => ['sometimes', Rule::enum(PaymentStatusEnum::class)],
            'payment_method' => [
                'sometimes',
                Rule::enum(PaymentMethodEnum::class),
                Rule::requiredIf(fn() => $this->payment_status === PaymentStatusEnum::PAID->value),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $booking = Booking::find($this->booking_id);

            if (! $booking) {
                $validator->errors()->add('booking_id', 'Agendamento não encontrado.');
                return;
            }

            // Prevent changing payment status if payment method is from_app
            // Only bookings paid through the app cannot have their payment status changed
            if ($booking->payment_method !== null && $booking->payment_method === PaymentMethodEnum::FROM_APP && ($this->has('payment_status') || $this->has('payment_method'))) {
                $validator->errors()->add('payment_status', 'Não é possível alterar o status de pagamento de um agendamento pago pelo aplicativo.');
            }

            // Validate court belongs to tenant if court is being changed
            if ($this->has('court_id')) {
                $court = Court::find($this->court_id);

                if (! $court) {
                    $validator->errors()->add('court_id', 'Quadra não encontrada.');
                    return;
                }

                if ($court->tenant_id !== $booking->tenant_id) {
                    $validator->errors()->add('court_id', 'A quadra selecionada não pertence ao mesmo estabelecimento.');
                }
            }

            // Validate slots if provided
            if (!$validator->errors()->any() && $this->has('slots')) {
                $this->validateSlotsContiguity($validator);
                $this->validateSlotsAvailability($validator, $booking);
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
    protected function validateSlotsAvailability($validator, Booking $booking)
    {
        $courtId = $this->input('court_id') ?? $booking->court_id;
        $date = $this->input('start_date') ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');
        $slots = $this->input('slots');

        $court = Court::with('tenant')->find($courtId);
        
        if (!$court) {
            return;
        }

        // Check each requested slot using checkAvailability
        foreach ($slots as $index => $requestedSlot) {
            $error = $court->checkAvailability(
                $date,
                $requestedSlot['start'],
                $requestedSlot['end'],
                $booking->user_id, // excludeUserId to allow sequential bookings for same user
                $booking->id // excludeBookingId (to allow updating same booking)
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
