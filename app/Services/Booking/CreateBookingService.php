<?php

namespace App\Services\Booking;

use App\Actions\General\EasyHashAction;
use App\Actions\General\QrCodeAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\UserTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateBookingService
{
    /**
     * Create a new booking
     *
     * @param Tenant $tenant The tenant for the booking
     * @param array $data Validated booking data containing:
     *   - court_id: int (decoded)
     *   - client_id: int (decoded)
     *   - start_date: string (Y-m-d format)
     *   - start_time: string (H:i format)
     *   - end_time: string (H:i format)
     *   - price: int|null (optional, in cents)
     *   - status: BookingStatusEnum|null (optional)
     *   - payment_status: PaymentStatusEnum|null (optional)
     *   - payment_method: PaymentMethodEnum|null (optional)
     * @return Booking The created booking with court relationship loaded
     * @throws HttpException If validation fails or booking cannot be created
     */
    public function create(Tenant $tenant, array $data): Booking
    {
        try {
            return DB::transaction(function () use ($tenant, $data) {
                // Validate and get court
                $court = $this->validateCourt($tenant, $data['court_id']);

                // Validate client
                $clientId = $this->validateClient($data['client_id']);

                // Check availability
                $this->checkAvailability($court, $data['start_date'], $data['start_time'], $data['end_time'], $clientId);

                // Prepare booking data
                $bookingData = $this->prepareBookingData($tenant, $data, $court, $clientId);

                // Create booking
                $booking = Booking::create($bookingData);

                // Link user to tenant if not already linked
                $this->linkUserToTenant($clientId, $tenant->id);

                // Generate QR code
                $this->generateQrCode($tenant, $booking);

                // Load relationships
                $booking->load('court');

                return $booking;
            });
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions (validation errors)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao criar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'data' => $data,
            ]);

            throw new HttpException(500, 'Erro inesperado ao criar agendamento. Por favor, tente novamente.');
        }
    }

    /**
     * Validate that the court exists and belongs to the tenant
     *
     * @param Tenant $tenant
     * @param int $courtId
     * @return Court
     * @throws HttpException
     */
    protected function validateCourt(Tenant $tenant, int $courtId): Court
    {
        $court = Court::find($courtId);

        if (!$court || $court->tenant_id !== $tenant->id) {
            throw new HttpException(400, 'Quadra inválida.');
        }

        return $court;
    }

    /**
     * Validate that the client exists
     *
     * @param int $clientId
     * @return int
     * @throws HttpException
     */
    protected function validateClient(int $clientId): int
    {
        if (!$clientId) {
            throw new HttpException(400, 'Cliente inválido ou não fornecido.');
        }

        return $clientId;
    }

    /**
     * Check if the requested time slot is available
     *
     * @param Court $court
     * @param string $startDate
     * @param string $startTime
     * @param string $endTime
     * @param int $clientId
     * @throws HttpException
     */
    protected function checkAvailability(Court $court, string $startDate, string $startTime, string $endTime, int $clientId): void
    {
        $availabilityError = $court->checkAvailability($startDate, $startTime, $endTime, $clientId);

        if ($availabilityError) {
            throw new HttpException(400, $availabilityError);
        }
    }

    /**
     * Prepare booking data array
     *
     * @param Tenant $tenant
     * @param array $data
     * @param Court $court
     * @param int $clientId
     * @return array
     */
    protected function prepareBookingData(Tenant $tenant, array $data, Court $court, int $clientId): array
    {
        return [
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'user_id' => $clientId,
            'currency_id' => CurrencyModel::where('code', $tenant->currency)->first()?->id ?? 1,
            'start_date' => $data['start_date'],
            'end_date' => $data['start_date'], // Single day booking for now
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'price' => $data['price'] ?? 0,
            'status' => $data['status'] ?? BookingStatusEnum::CONFIRMED,
            'payment_status' => $data['payment_status'] ?? PaymentStatusEnum::PENDING,
            'payment_method' => $data['payment_method'] ?? null,
        ];
    }

    /**
     * Link user to tenant if not already linked
     *
     * @param int $userId
     * @param int $tenantId
     * @return void
     */
    protected function linkUserToTenant(int $userId, int $tenantId): void
    {
        UserTenant::firstOrCreate([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Generate QR code for the booking
     *
     * @param Tenant $tenant
     * @param Booking $booking
     * @return void
     */
    protected function generateQrCode(Tenant $tenant, Booking $booking): void
    {
        try {
            $bookingIdHashed = EasyHashAction::encode($booking->id, 'booking-id');
            $qrCodeInfo = QrCodeAction::create(
                $tenant->id,
                $booking->id,
                $bookingIdHashed
            );

            // Update booking with QR code path
            $booking->update(['qr_code' => $qrCodeInfo->url]);
        } catch (\Exception $qrException) {
            // Log QR generation error but don't fail the booking
            Log::error('Failed to generate QR code for booking', [
                'booking_id' => $booking->id,
                'tenant_id' => $tenant->id,
                'error' => $qrException->getMessage(),
            ]);
        }
    }
}

