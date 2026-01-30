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

        // Handle slots input and identify contiguous blocks
        if ($this->has('slots') && is_array($this->input('slots'))) {
            $slots = $this->input('slots');
            if (count($slots) > 0) {
                // If we want to split, we should probably only set start_time/end_time 
                // for the FIRST contiguous block for the main booking update.
                $firstSlot = $slots[0];
                $this->merge([
                    'start_time' => $firstSlot['start'],
                    'end_time'   => $firstSlot['end'],
                ]);
                
                // We'll calculate the actual end_time of the first block in the service
                // or here if we detect the first block.
            }
        }

        // Handle Timezone Conversion
        if ($this->hasAny(['start_date', 'start_time', 'end_time', 'slots'])) {
            $bookingId = $this->booking_id ?? EasyHashAction::decode($this->route('booking_id'), 'booking-id');
            $booking = Booking::find($bookingId);

            if ($booking) {
                $timezoneService = app(\App\Services\TimezoneService::class);
                $newStartDate = $this->input('start_date') ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');

                // Helper to convert time from User TZ to UTC
                $convertToUtc = function($timeString) use ($timezoneService, $newStartDate) {
                    $dateTimeString = $newStartDate . ' ' . $timeString;
                    $utcDateTime = $timezoneService->toUTC($dateTimeString);
                    return $utcDateTime ? $utcDateTime->format('H:i') : $timeString;
                };

                // Convert primary fields if present
                if ($this->has('start_time')) {
                    $this->merge(['start_time' => $convertToUtc($this->input('start_time'))]);
                }
                if ($this->has('end_time')) {
                    $this->merge(['end_time' => $convertToUtc($this->input('end_time'))]);
                }

                // If slots are provided, convert EACH slot start/end
                if ($this->has('slots') && is_array($this->input('slots'))) {
                    $conversionResult = $timezoneService->convertSlotsToUtc(
                        $newStartDate,
                        $this->input('slots')
                    );
                    
                    if (!empty($conversionResult['slots'])) {
                        $this->merge([
                            'slots' => $conversionResult['slots'],
                            'start_date' => $conversionResult['start_date'],
                            'start_time' => $conversionResult['slots'][0]['start'],
                            'end_time' => $conversionResult['slots'][count($conversionResult['slots']) - 1]['end'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @bodyParam slots array Os horários a serem reservados. Se forem enviados horários não contíguos (com intervalos), o sistema poderá criar agendamentos adicionais separados automaticamente.
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
            if ($booking->payment_method === PaymentMethodEnum::FROM_APP && 
                $booking->payment_status === PaymentStatusEnum::PAID && 
                ($this->has('payment_status') || $this->has('payment_method'))) {
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
            // Note: We don't validate contiguity here. If slots are non-contiguous,
            // the frontend should create separate bookings for each contiguous range.
            if (!$validator->errors()->any() && $this->has('slots')) {
                $this->validateSlotsAvailability($validator, $booking);
            }
        });
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
