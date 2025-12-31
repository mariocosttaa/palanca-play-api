<?php

namespace App\Services\Booking;

use App\Enums\BookingApiContextEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateBookingService
{
    /**
     * Update an existing booking
     *
     * @param Tenant $tenant The tenant for the booking
     * @param int $bookingId The decoded booking ID
     * @param array $data Validated update data containing:
     *   - court_id: int|null (optional, decoded)
     *   - start_date: string|null (optional, Y-m-d format)
     *   - start_time: string|null (optional, H:i format)
     *   - end_time: string|null (optional, H:i format)
     *   - price: int|null (optional, in cents)
     *   - status: BookingStatusEnum|null (optional)
     *   - payment_status: PaymentStatusEnum|null (optional)
     *   - payment_method: PaymentMethodEnum|null (optional)
     * @param BookingApiContextEnum $apiContext The API context (mobile or business)
     * @return Booking The updated booking with court relationship loaded
     * @throws HttpException If validation fails or booking cannot be updated
     */
    public function update(Tenant $tenant, int $bookingId, array $data, BookingApiContextEnum $apiContext = BookingApiContextEnum::BUSINESS): Booking
    {
        try {
            // Find booking
            $booking = $this->findBooking($tenant, $bookingId);

            // Check if booking can be updated
            $this->validateBookingCanBeUpdated($booking);

            // Check availability if dates/times/court are changing
            if ($this->shouldCheckAvailability($data)) {
                $this->checkAvailabilityForUpdate($booking, $data);
            }

            // Update booking
            $booking->update($data);

            // Load relationships
            $booking->load('court');

            return $booking;
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions (validation errors)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro ao atualizar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'booking_id' => $bookingId,
                'data' => $data,
            ]);

            throw new HttpException(400, 'Erro ao atualizar agendamento');
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
     * Validate that booking can be updated
     *
     * @param Booking $booking
     * @throws HttpException
     */
    protected function validateBookingCanBeUpdated(Booking $booking): void
    {
        // Prevent any update if booking is marked as present
        if ($booking->present === true) {
            throw new HttpException(
                400,
                'Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.'
            );
        }
    }

    /**
     * Check if availability should be checked based on data changes
     *
     * @param array $data
     * @return bool
     */
    protected function shouldCheckAvailability(array $data): bool
    {
        return isset($data['court_id']) ||
            isset($data['start_date']) ||
            isset($data['start_time']) ||
            isset($data['end_time']);
    }

    /**
     * Check availability for update operation
     *
     * @param Booking $booking
     * @param array $data
     * @throws HttpException
     */
    protected function checkAvailabilityForUpdate(Booking $booking, array $data): void
    {
        // Get current values if not provided in update
        $startDate = $data['start_date'] ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');
        $startTime = $data['start_time'] ?? \Carbon\Carbon::parse($booking->start_time)->format('H:i');
        $endTime = $data['end_time'] ?? \Carbon\Carbon::parse($booking->end_time)->format('H:i');

        // Get court - use new court if provided, otherwise use current court
        if (isset($data['court_id'])) {
            $court = $this->validateCourt($booking->tenant_id, $data['court_id']);
        } else {
            $court = $booking->court;
        }

        $availabilityError = $court->checkAvailability(
            $startDate,
            $startTime,
            $endTime,
            $booking->user_id, // Exclude user buffer check if applicable
            $booking->id       // Exclude this booking from collision check
        );

        if ($availabilityError) {
            throw new HttpException(400, $availabilityError);
        }
    }

    /**
     * Validate that the court exists and belongs to the tenant
     *
     * @param int $tenantId
     * @param int $courtId
     * @return Court
     * @throws HttpException
     */
    protected function validateCourt(int $tenantId, int $courtId): Court
    {
        $court = Court::find($courtId);

        if (!$court || $court->tenant_id !== $tenantId) {
            throw new HttpException(400, 'Quadra inválida.');
        }

        return $court;
    }
}

