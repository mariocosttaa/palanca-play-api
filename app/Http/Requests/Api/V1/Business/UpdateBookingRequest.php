<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'sometimes|date|after_or_equal:today',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'price' => 'sometimes|integer|min:0',
            'paid_at_venue' => 'sometimes|boolean',
            'is_paid' => 'sometimes|boolean',
            'is_cancelled' => 'sometimes|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $booking = \App\Models\Booking::find($this->booking_id);

            if (!$booking) {
                $validator->errors()->add('booking_id', 'Agendamento não encontrado.');
                return;
            }

            // Prevent changing payment fields if already paid (reserved for automatic payments)
            if ($booking->is_paid && ($this->has('is_paid') || $this->has('paid_at_venue'))) {
                $validator->errors()->add('is_paid', 'Não é possível alterar o status de pagamento de um agendamento já pago.');
            }
        });
    }
}
