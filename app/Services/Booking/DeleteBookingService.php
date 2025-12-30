<?php

namespace App\Services\Booking;

use App\Enums\PaymentMethodEnum;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteBookingService
{
    /**
     * Delete a booking
     *
     * @param Tenant $tenant The tenant for the booking
     * @param int $bookingId The decoded booking ID
     * @return void
     * @throws HttpException If validation fails or booking cannot be deleted
     */
    public function delete(Tenant $tenant, int $bookingId): void
    {
        try {
            // Find booking
            $booking = $this->findBooking($tenant, $bookingId);

            // Validate that booking can be deleted
            $this->validateBookingCanBeDeleted($booking);

            // Delete booking (QR code deletion is handled by Booking model's boot method)
            $booking->delete();
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions (validation errors)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro ao remover agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'booking_id' => $bookingId,
            ]);

            throw new HttpException(400, 'Erro ao remover agendamento');
        }
    }

    /**
     * Find booking by ID and tenant
     *
     * @param Tenant $tenant
     * @param int $bookingId
     * @return Booking
     * @throws HttpException
     */
    protected function findBooking(Tenant $tenant, int $bookingId): Booking
    {
        $booking = Booking::forTenant($tenant->id)->find($bookingId);

        if (!$booking) {
            throw new HttpException(404, 'Agendamento não encontrado');
        }

        return $booking;
    }

    /**
     * Validate that booking can be deleted
     *
     * @param Booking $booking
     * @throws HttpException
     */
    protected function validateBookingCanBeDeleted(Booking $booking): void
    {
        // Prevent deletion if booking is marked as present
        if ($booking->present === true) {
            throw new HttpException(
                400,
                'Não é possível excluir um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.'
            );
        }

        // Prevent deletion if booking was paid from app (can only cancel, not delete)
        if ($booking->payment_method !== null && $booking->payment_method === PaymentMethodEnum::FROM_APP) {
            throw new HttpException(
                400,
                'Não é possível excluir um agendamento que foi pago pelo aplicativo. Você pode cancelar o agendamento alterando o status para cancelado.'
            );
        }
    }
}

