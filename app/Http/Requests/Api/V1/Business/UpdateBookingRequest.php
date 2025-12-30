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
            'start_time'     => 'sometimes|date_format:H:i',
            'end_time'       => 'sometimes|date_format:H:i|after:start_time',
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
        });
    }
}
