<?php

namespace App\Services\Booking;

use App\Actions\General\QrCodeAction;
use App\Enums\BookingApiContextEnum;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CancelBookingService
{
    /**
     * Cancel a booking
     *
     * @param Tenant $tenant The tenant for the booking
     * @param int $bookingId The decoded booking ID
     * @param BookingApiContextEnum $apiContext The API context (mobile or business)
     * @return Booking The cancelled booking
     * @throws HttpException If validation fails or booking cannot be cancelled
     */
    public function cancel(Tenant $tenant, int $bookingId, BookingApiContextEnum $apiContext = BookingApiContextEnum::BUSINESS): Booking
    {
        try {
            // Find booking
            $booking = $this->findBooking($tenant, $bookingId);

            // Validate that booking can be cancelled
            $this->validateBookingCanBeCancelled($booking);

            // Mark as cancelled
            $booking->update(['status' => BookingStatusEnum::CANCELLED]);

            // Delete QR code if exists
            if ($booking->qr_code) {
                try {
                    QrCodeAction::delete($booking->tenant_id, $booking->qr_code);
                } catch (\Exception $qrException) {
                    Log::error('Failed to delete QR code for cancelled booking', [
                        'booking_id' => $booking->id,
                        'tenant_id'  => $tenant->id,
                        'error'      => $qrException->getMessage(),
                    ]);
                }
            }

            // Load relationships
            $booking->load('court');

            return $booking;
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions (validation errors)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro ao cancelar agendamento', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'tenant_id'  => $tenant->id,
                'booking_id' => $bookingId,
            ]);

            throw new HttpException(400, 'Erro ao cancelar agendamento');
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
     * Validate that booking can be cancelled
     *
     * @param Booking $booking
     * @throws HttpException
     */
    protected function validateBookingCanBeCancelled(Booking $booking): void
    {
        // Check if booking is already cancelled
        if ($booking->status === BookingStatusEnum::CANCELLED) {
            throw new HttpException(400, 'Este agendamento já foi cancelado');
        }

        // For mobile API, prevent cancelling past bookings
        // (Business API might have different rules, but for now we apply same rule)
        if ($booking->start_date < now()->format('Y-m-d')) {
            throw new HttpException(400, 'Não é possível cancelar agendamentos passados');
        }
    }
}


