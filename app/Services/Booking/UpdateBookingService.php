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
     * Group individual 30/60min slots into contiguous blocks, considering buffer time
     * Using string comparisons where possible for performance
     */
    protected function groupSlotsIntoBlocks(array $slots, int $bufferMinutes = 0): array
    {
        if (empty($slots)) {
            return [];
        }

        if (count($slots) === 1) {
            return [[$slots[0]]];
        }

        // Sort slots by start time
        usort($slots, fn($a, $b) => strcmp($a['start'], $b['start']));

        $blocks = [];
        $currentBlock = [$slots[0]];
        $prevEndStr = $slots[0]['end'];

        for ($i = 1; $i < count($slots); $i++) {
            $currentStartStr = $slots[$i]['start'];

            $isGroupable = ($prevEndStr === $currentStartStr);
            
            if (!$isGroupable && $bufferMinutes > 0) {
                $prevEnd = \Carbon\Carbon::parse($prevEndStr);
                $currentStart = \Carbon\Carbon::parse($currentStartStr);
                if ($prevEnd->copy()->addMinutes($bufferMinutes)->format('H:i') === $currentStartStr) {
                    $isGroupable = true;
                }
            }

            if ($isGroupable) {
                $currentBlock[] = $slots[$i];
            } else {
                $blocks[] = $currentBlock;
                $currentBlock = [$slots[$i]];
            }
            $prevEndStr = $slots[$i]['end'];
        }
        $blocks[] = $currentBlock;

        return $blocks;
    }
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
     * @throws HttpException If validation fails or booking cannot be updated
     */
    /**
     * Update an existing booking
     *
     * @param Tenant $tenant The tenant for the booking
     * @param int $bookingId The decoded booking ID
     * @param array $data Validated update data
     * @param BookingApiContextEnum $apiContext The API context (mobile or business)
     * @throws HttpException If validation fails or booking cannot be updated
     */
    public function update(Tenant $tenant, int $bookingId, array $data, BookingApiContextEnum $apiContext = BookingApiContextEnum::BUSINESS): Booking
    {
        try {
            // Find booking
            $booking = $this->findBooking($tenant, $bookingId);

            // Check if booking can be updated
            $this->validateBookingCanBeUpdated($booking);

            // If slots are provided, handle potential splitting
            if (isset($data['slots']) && is_array($data['slots']) && count($data['slots']) > 0) {
                // Fetch court buffer time
                $court = isset($data['court_id']) ? $this->validateCourt($tenant->id, $data['court_id']) : $booking->court;
                return $this->handleSlotsUpdate($tenant, $booking, $data, $court, $apiContext);
            }

            // Standard update (no slots, only start_time/end_time)
            if ($this->shouldCheckAvailability($data)) {
                $this->checkAvailabilityForUpdate($booking, $data);
            }

            // Update booking
            $booking->update($data);
            $booking->load('court');

            // Ensure QR code exists on update
            if (!$booking->qr_code) {
                try {
                    $bookingIdHashed = \App\Actions\General\EasyHashAction::encode($booking->id, 'booking-id');
                    $qrCodeInfo      = \App\Actions\General\QrCodeAction::create(
                        $tenant->id,
                        $booking->id,
                        $bookingIdHashed
                    );
                    $booking->update(['qr_code' => $qrCodeInfo->url]);
                } catch (\Exception $e) {
                    Log::error('Failed to generate QR code on booking update', [
                        'booking_id' => $booking->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return $booking;
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
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
     * Handle update when slots are provided, potentially splitting into multiple bookings
     */
    protected function handleSlotsUpdate(Tenant $tenant, Booking $booking, array $data, Court $court, BookingApiContextEnum $apiContext): Booking
    {
        $slots = $data['slots'];
        $bufferMinutes = $court->courtType->buffer_time_minutes ?? 0;
        $blocks = $this->groupSlotsIntoBlocks($slots, $bufferMinutes);

        // Process the first block (updates the current booking)
        $firstBlock = $blocks[0];
        $this->updateBookingWithBlock($booking, $firstBlock, $data);

        // Process subsequent blocks (create new bookings)
        if (count($blocks) > 1) {
            $this->createNewBookingsForRemainingBlocks($tenant, $booking, $blocks, $data, $apiContext);
        }

        $booking->load('court');
        return $booking;
    }

    /**
     * Update the existing booking with a specific block of slots
     */
    protected function updateBookingWithBlock(Booking $booking, array $block, array $originalData): void
    {
        $startTime = $block[0]['start'];
        $endTime = $block[count($block) - 1]['end'];
        $startDate = $originalData['start_date'] ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');
        
        $updateData = array_merge($originalData, [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'slots' => $block,
        ]);

        // Recalculate price for this block if price was provided in original data
        if (isset($originalData['price'])) {
            $court = isset($originalData['court_id']) ? Court::find($originalData['court_id']) : $booking->court;
            if ($court && $court->courtType) {
                $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                $updateData['price'] = $pricePerInterval * count($block);
            }
        }

        // Check availability for this block
        $this->checkAvailabilityForUpdate($booking, $updateData);

        // Update the booking
        $booking->update($updateData);

        // Ensure QR code exists on update
        if (!$booking->qr_code) {
            try {
                $bookingIdHashed = \App\Actions\General\EasyHashAction::encode($booking->id, 'booking-id');
                $qrCodeInfo      = \App\Actions\General\QrCodeAction::create(
                    $booking->tenant_id,
                    $booking->id,
                    $bookingIdHashed
                );
                $booking->update(['qr_code' => $qrCodeInfo->url]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to generate QR code on booking update with slots', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create new bookings for non-contiguous blocks
     */
    protected function createNewBookingsForRemainingBlocks(Tenant $tenant, Booking $originalBooking, array $blocks, array $originalData, BookingApiContextEnum $apiContext): void
    {
        // Skip the first block as it updated the original booking
        for ($i = 1; $i < count($blocks); $i++) {
            $block = $blocks[$i];
            $startTime = $block[0]['start'];
            $endTime = $block[count($block) - 1]['end'];
            $startDate = $originalData['start_date'] ?? \Carbon\Carbon::parse($originalBooking->start_date)->format('Y-m-d');

            $newBookingData = [
                'tenant_id' => $tenant->id,
                'court_id' => $originalData['court_id'] ?? $originalBooking->court_id,
                'user_id' => $originalBooking->user_id,
                'currency_id' => $originalBooking->currency_id,
                'start_date' => $startDate,
                'end_date' => $startDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $originalData['status'] ?? $originalBooking->status,
                'payment_status' => $originalData['payment_status'] ?? $originalBooking->payment_status,
                'payment_method' => $originalData['payment_method'] ?? $originalBooking->payment_method,
            ];

            // Calculate price for this block
            $court = Court::find($newBookingData['court_id']);
            if ($court && $court->courtType) {
                $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                $newBookingData['price'] = $pricePerInterval * count($block);
            }

            // Check availability (exclude user buffer check if applicable, but don't need to exclude a booking ID since it's new)
            $availabilityError = $court->checkAvailability(
                $startDate,
                $startTime,
                $endTime,
                $originalBooking->user_id,
                $originalBooking->id
            );

            if ($availabilityError) {
                throw new HttpException(400, "Conflito no segundo bloco de horários: " . $availabilityError);
            }

            // Create the new booking
            $newBooking = Booking::create($newBookingData);
            
            // Generate QR code for the new booking
            try {
                $bookingIdHashed = \App\Actions\General\EasyHashAction::encode($newBooking->id, 'booking-id');
                $qrCodeInfo      = \App\Actions\General\QrCodeAction::create(
                    $tenant->id,
                    $newBooking->id,
                    $bookingIdHashed
                );

                // Update booking with QR code path
                $newBooking->update(['qr_code' => $qrCodeInfo->url]);
            } catch (\Exception $qrException) {
                // Log QR generation error but don't fail the booking
                Log::error('Failed to generate QR code for split booking', [
                    'booking_id' => $newBooking->id,
                    'tenant_id'  => $tenant->id,
                    'error'      => $qrException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Find booking by ID and tenant
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
     */
    protected function validateBookingCanBeUpdated(Booking $booking): void
    {
        if ($booking->present === true) {
            throw new HttpException(
                400,
                'Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.'
            );
        }
    }

    /**
     * Check if availability should be checked
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
     */
    protected function checkAvailabilityForUpdate(Booking $booking, array $data): void
    {
        $startDate = $data['start_date'] ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');
        $startTime = $data['start_time'] ?? \Carbon\Carbon::parse($booking->start_time)->format('H:i');
        $endTime = $data['end_time'] ?? \Carbon\Carbon::parse($booking->end_time)->format('H:i');

        if (isset($data['court_id'])) {
            $court = $this->validateCourt($booking->tenant_id, $data['court_id']);
        } else {
            $court = $booking->court;
        }

        $availabilityError = $court->checkAvailability(
            $startDate,
            $startTime,
            $endTime,
            $booking->user_id,
            $booking->id
        );

        if ($availabilityError) {
            throw new HttpException(400, $availabilityError);
        }
    }

    /**
     * Validate court
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
