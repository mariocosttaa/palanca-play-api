<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Actions\General\EasyHashAction;
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
            'court_id' => ['sometimes', 'integer', Rule::exists(\App\Models\Court::class, 'id')],
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

            // Validate court belongs to tenant if court is being changed
            if ($this->has('court_id')) {
                $court = \App\Models\Court::find($this->court_id);
                
                if (!$court) {
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
