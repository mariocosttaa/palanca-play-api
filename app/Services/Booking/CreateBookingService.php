<?php
namespace App\Services\Booking;

use App\Actions\General\EasyHashAction;
use App\Actions\General\QrCodeAction;
use App\Enums\BookingApiContextEnum;
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
     * @param BookingApiContextEnum $apiContext The API context (mobile or business)
     * @return Booking The created booking with court relationship loaded
     * @throws HttpException If validation fails or booking cannot be created
     */
    public function create(Tenant $tenant, array $data, BookingApiContextEnum $apiContext = BookingApiContextEnum::BUSINESS): Booking
    {
        try {
            return DB::transaction(function () use ($tenant, $data, $apiContext) {
                // Validate and get basic entities once
                $court = $this->validateCourt($tenant, $data['court_id']);
                $clientId = $this->validateClient($data['client_id']);

                // If slots are provided, handle potential splitting
                if (isset($data['slots']) && is_array($data['slots']) && count($data['slots']) > 0) {
                    $bufferMinutes = $court->courtType->buffer_time_minutes ?? 0;
                    $blocks = $this->groupSlotsIntoBlocks($data['slots'], $bufferMinutes);
                    
                    // The first block will be the "primary" booking
                    $firstBlock = $blocks[0];
                    $data['start_time'] = $firstBlock[0]['start'];
                    $data['end_time'] = $firstBlock[count($firstBlock) - 1]['end'];
                    
                    if ($apiContext === BookingApiContextEnum::MOBILE || isset($data['price'])) {
                        $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                        $data['price'] = $pricePerInterval * count($firstBlock);
                    }

                    // Create primary booking
                    $booking = $this->createSingleBooking($tenant, $data, $court, $clientId, $apiContext);

                    // If more blocks exist, create separate bookings
                    if (count($blocks) > 1) {
                        $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                        for ($i = 1; $i < count($blocks); $i++) {
                            $block = $blocks[$i];
                            $blockData = $data;
                            $blockData['start_time'] = $block[0]['start'];
                            $blockData['end_time'] = $block[count($block) - 1]['end'];
                            
                            if ($apiContext === BookingApiContextEnum::MOBILE || isset($data['price'])) {
                                $blockData['price'] = $pricePerInterval * count($block);
                            }
                            
                            $this->createSingleBooking($tenant, $blockData, $court, $clientId, $apiContext);
                        }
                    }

                    return $booking;
                }

                // Standard creation for single time range
                return $this->createSingleBooking($tenant, $data, $court, $clientId, $apiContext);
            });
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions (validation errors)
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao criar agendamento', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'data'      => $data,
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

        if (! $court || $court->tenant_id !== $tenant->id) {
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
        if (! $clientId) {
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
     * @param BookingApiContextEnum $apiContext
     * @return array
     */
    protected function prepareBookingData(Tenant $tenant, array $data, Court $court, int $clientId, BookingApiContextEnum $apiContext): array
    {
        // Determine status based on API context and tenant settings
        $status = $data['status'] ?? null;
        
        // For mobile API, respect tenant auto-confirmation setting
        if ($status === null && $apiContext === BookingApiContextEnum::MOBILE) {
            $status = $tenant->auto_confirm_bookings 
                ? BookingStatusEnum::CONFIRMED 
                : BookingStatusEnum::PENDING;
        }
        
        // For business API, default to CONFIRMED if not specified
        if ($status === null) {
            $status = BookingStatusEnum::CONFIRMED;
        }

        return [
            'tenant_id'      => $tenant->id,
            'court_id'       => $court->id,
            'user_id'        => $clientId,
            'currency_id'    => CurrencyModel::where('code', $tenant->currency)->first()?->id ?? 1,
            'start_date'     => $data['start_date'],
            'end_date'       => $data['start_date'], // Single day booking for now
            'start_time'     => $data['start_time'],
            'end_time'       => $data['end_time'],
            'price'          => $data['price'] ?? 0,
            'status'         => $status,
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
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Helper to create a single booking within the transaction
     */
    protected function createSingleBooking(Tenant $tenant, array $data, Court $court, int $clientId, BookingApiContextEnum $apiContext): Booking
    {
        // Check availability
        $this->checkAvailability($court, $data['start_date'], $data['start_time'], $data['end_time'], $clientId);

        // Prepare booking data
        $bookingData = $this->prepareBookingData($tenant, $data, $court, $clientId, $apiContext);

        // Create booking
        $booking = Booking::create($bookingData);

        // Link user to tenant if not already linked
        $this->linkUserToTenant($clientId, $tenant->id);

        // Generate QR code
        $this->generateQrCode($tenant, $booking);

        // Load relationships
        $booking->load('court');

        return $booking;
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
            $qrCodeInfo      = QrCodeAction::create(
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
                'tenant_id'  => $tenant->id,
                'error'      => $qrException->getMessage(),
            ]);
        }
    }
}
