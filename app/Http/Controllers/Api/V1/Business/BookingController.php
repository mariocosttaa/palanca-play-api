<?php
namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateBookingRequest;
use App\Http\Requests\Api\V1\Business\UpdateBookingRequest;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use App\Services\Booking\CreateBookingService;
use App\Services\Booking\DeleteBookingService;
use App\Services\Booking\UpdateBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @tags [API-BUSINESS] Bookings
 */
class BookingController extends Controller
{
    public function __construct(
        protected CreateBookingService $createBookingService,
        protected UpdateBookingService $updateBookingService,
        protected DeleteBookingService $deleteBookingService,
    ) {
    }
    /**
     * Get a list of bookings with optional filters
     *
     * @queryParam search string Search by client name or court name. Example: "John"
     * @queryParam date string Filter by specific date (Y-m-d). Example: "2024-12-19"
     * @queryParam start_date string Start date for range filter. Example: "2024-12-01"
     * @queryParam end_date string End date for range filter. Example: "2024-12-31"
     * @queryParam court_id string Filter by court (hashed ID). Example: "Xy7z..."
     * @queryParam status string Filter by booking status (confirmed, pending, cancelled). Example: "confirmed"
     * @queryParam payment_status string Filter by payment status (paid, pending). Example: "paid"
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;
            $query  = Booking::forTenant($tenant->id)->with(['user', 'court', 'currency']);

            // Date filters
            if ($request->has('date')) {
                $date = \Carbon\Carbon::parse($request->date)->format('Y-m-d');
                $query->onDate($date);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->betweenDates($request->start_date, $request->end_date);
            }

            // Court filter
            if ($request->has('court_id')) {
                $courtId = EasyHashAction::decode($request->court_id, 'court-id');
                $query->forCourt($courtId);
            }

            // Status filter
            if ($request->has('status')) {
                $status = strtolower($request->status);
                if (in_array($status, ['confirmed', 'pending', 'cancelled'])) {
                    $statusEnum = match ($status) {
                        'confirmed' => BookingStatusEnum::CONFIRMED,
                        'pending'   => BookingStatusEnum::PENDING,
                        'cancelled' => BookingStatusEnum::CANCELLED,
                    };
                    $query->where('status', $statusEnum);
                }
            }

            // Payment status filter
            if ($request->has('payment_status')) {
                $paymentStatus = strtolower($request->payment_status);
                if (in_array($paymentStatus, ['paid', 'pending'])) {
                    $paymentStatusEnum = match ($paymentStatus) {
                        'paid'    => PaymentStatusEnum::PAID,
                        'pending' => PaymentStatusEnum::PENDING,
                    };
                    $query->where('payment_status', $paymentStatusEnum);
                }
            }

            // Search functionality
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    // Search by client name
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('surname', 'LIKE', "%{$search}%");
                    })
                    // Search by court name
                        ->orWhereHas('court', function ($courtQuery) use ($search) {
                            $courtQuery->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            $bookings = $query->orderBy('start_date', 'desc')
                ->orderBy('start_time', 'desc')
                ->paginate(20);

            return BookingResource::collection($bookings);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao listar agendamentos', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao listar agendamentos', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'tenant_id' => $request->tenant->id ?? null,
                'filters'   => $request->all(),
            ]);
            abort(500, 'Erro inesperado ao listar agendamentos. Por favor, tente novamente.');
        }
    }

    /**
     * Create a new booking
     *
     * Creates a new booking for a court at a specific date and time.
     *
     * **Note:** When using the `slots` parameter, the price is automatically calculated based on the
     * number of slots and the court's price_per_interval.
     * 
     * **Slot Splitting:**
     * Se forem enviados horários não contíguos (com intervalos), o sistema **automaticamente dividirá** a reserva em múltiplos agendamentos separados. Por exemplo, enviar 10:00-11:00 e 12:00-13:00 resultará em duas reservas distintas.
     *
     */
    public function store(CreateBookingRequest $request): BookingResource
    {
        try {
            $tenant = $request->tenant;
            $data   = $request->validated();

            // Always calculate price from slots when provided (ignore any price from frontend)
            if (isset($data['slots']) && is_array($data['slots'])) {
                $court = \App\Models\Court::with('courtType')->find($data['court_id']);
                if ($court && $court->courtType) {
                    $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                    $data['price']    = $pricePerInterval * count($data['slots']);
                }
            }

            $booking = $this->createBookingService->create($tenant, $data, \App\Enums\BookingApiContextEnum::BUSINESS);

            return new BookingResource($booking);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao criar agendamento', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao criar agendamento', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'tenant_id' => $request->tenant->id ?? null,
                'data'      => $request->validated(),
            ]);
            abort(500, 'Erro inesperado ao criar agendamento. Por favor, tente novamente.');
        }
    }

    /**
     * Get a specific booking by ID
     *
     * Retrieves detailed information about a specific booking.
     */
    public function show(Request $request, string $tenantId, $bookingId): BookingResource
    {
        try {
            $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $booking   = Booking::forTenant($request->tenant->id)->with(['user', 'court'])->find($bookingId);

            if (! $booking) {
                abort(404, 'Agendamento não encontrado');
            }

            return new BookingResource($booking);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao buscar agendamento', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'booking_id'  => $bookingId ?? null,
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao buscar agendamento', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'booking_id' => $bookingId ?? null,
                'tenant_id'  => $request->tenant->id ?? null,
            ]);
            abort(500, 'Erro inesperado ao buscar agendamento. Por favor, tente novamente.');
        }
    }

    /**
     * Update an existing booking
     *
     * Updates the details of an existing booking. Allows updating the court, start_date, start_time,
     * end_time, price, payment status, and status (including cancellation). The endpoint validates that
     * the new time slot is available on the specified court. The current booking is excluded from
     * availability checks, allowing the booking to keep its current time or be rescheduled to a
     * different court.
     *
     * **Slot Handling:**
     * - When using the `slots` parameter, the system identifies contiguous blocks of time.
     * - If non-contiguous slots are provided, the system **automatically splits** them into separate bookings.
     * - The existing booking is updated with the first contiguous block, and NEW bookings are created for subsequent blocks.
     * - Example: For `13:00-15:00` and `19:10-20:10`, the system creates two separate bookings.
     * - Price is automatically recalculated for each separate booking.
     * **Restrictions:**
     * - Bookings that have been marked as present cannot be updated, cancelled, or deleted.
     *   Users must contact support for assistance with these bookings.
     *
     * @bodyParam court_id string optional The court ID (hashed). Example: "2pX9g4KPNdz6dojQYaw16"
     * @bodyParam start_date string optional The booking start date (Y-m-d format). Example: "2026-02-02"
     * @bodyParam slots array Os horários a serem reservados. Se forem enviados horários não contíguos (com intervalos), o sistema criará múltiplos agendamentos separados automaticamente. Example: [{"start": "13:00", "end": "14:00"}, {"start": "14:00", "end": "15:00"}]
     * @bodyParam slots.*.start string The slot start time (H:i format). Example: "13:00"
     * @bodyParam slots.*.end string The slot end time (H:i format). Example: "14:00"
     * @bodyParam start_time string optional The booking start time (H:i format). Required if slots not provided. Example: "13:00"
     * @bodyParam end_time string optional The booking end time (H:i format). Required if slots not provided. Example: "15:00"
     * @bodyParam price integer optional The booking price in cents (auto-calculated from slots). Example: 5000
     * @bodyParam status string optional The booking status (confirmed, pending, cancelled). Example: "confirmed"
     * @bodyParam payment_status string optional The payment status (paid, pending). Example: "pending"
     * @bodyParam payment_method string optional The payment method (from_app, at_venue, cash, card, transfer). Required when payment_status is paid. Example: "card"
     *
     * @response 200 {"data": {"id": "ml62oqxbyKQBDdYn8PGWw", "court_id": "2pX9g4KPNdz6dojQYaw16", "user_id": "W39mX2xdzrz8a5lVQLRoE", "start_date": "2026-02-02", "end_date": "2026-02-02", "start_time": "13:00", "end_time": "15:00", "price": 5000, "currency": "aoa", "status": "confirmed", "payment_status": "pending", "created_at": "2026-01-29T14:37:53.000000Z"}}
     *
     * @responseExample 400 {"message": "Este horário já está reservado (13:00 - 14:00)."}
     * @responseExample 400 {"message": "Horário solicitado está fora do horário de funcionamento da quadra (09:00 - 21:00)."}
     * @responseExample 400 {"message": "Quadra não possui horário de funcionamento configurado para esta data."}
     * @responseExample 400 {"message": "Quadra marcada como indisponível nesta data."}
     * @responseExample 400 {"message": "Horário conflita com uma pausa configurada (12:00 - 13:00)."}
     * @responseExample 400 {"message": "Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência."}
     * @responseExample 404 {"message": "Agendamento não encontrado"}
     * @responseExample 422 {"message": "Os horários devem ser contíguos (sem intervalos entre eles)", "errors": {"slots": ["Os horários devem ser contíguos (sem intervalos entre eles)"]}}
     * @responseExample 500 {"message": "Erro inesperado ao atualizar agendamento. Por favor, tente novamente."}
     *
     * @return \App\Http\Resources\Business\V1\Specific\BookingResource
     */
    public function update(UpdateBookingRequest $request, string $tenantId, string $bookingId): BookingResource
    {
        try {
            // Use the decoded ID from the request (prepared in UpdateBookingRequest)
            $decodedBookingId = $request->booking_id;

            // Fallback if not in request (shouldn't happen if request is used)
            if (! $decodedBookingId) {
                $decodedBookingId = EasyHashAction::decode($bookingId, 'booking-id');
            }

            $tenant = $request->tenant;
            $data   = $request->validated();

            // Always recalculate price from slots when provided (ignore any price from frontend)
            if (isset($data['slots']) && is_array($data['slots'])) {
                // We need the court to calculate price. Use new court_id if provided, else use existing booking's court.
                $courtId = $data['court_id'] ?? null;
                
                if ($courtId) {
                    $court = \App\Models\Court::with('courtType')->find($courtId);
                } else {
                    // Fetch existing booking to get court_id if not in request
                     $existingBooking = Booking::with('court.courtType')->find($decodedBookingId);
                     $court = $existingBooking->court;
                }

                if ($court && $court->courtType) {
                    $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                    $data['price']    = $pricePerInterval * count($data['slots']);
                }
            }

            $booking = $this->updateBookingService->update($tenant, $decodedBookingId, $data, \App\Enums\BookingApiContextEnum::BUSINESS);

            return new BookingResource($booking);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao atualizar agendamento', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'booking_id'  => $decodedBookingId ?? $bookingId ?? null,
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao atualizar agendamento', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'booking_id' => $decodedBookingId ?? $bookingId ?? null,
                'tenant_id'  => $request->tenant->id ?? null,
                'data'       => $request->validated(),
            ]);
            abort(500, 'Erro inesperado ao atualizar agendamento. Por favor, tente novamente.');
        }
    }

    /**
     * Delete a booking
     *
     * Permanently removes a booking from the system.
     *
     * Restrictions:
     * - Bookings that have been marked as present cannot be deleted. Users must contact
     *   support for assistance with these bookings.
     * - Bookings that were paid from the app (payment_method = 'from_app') cannot be
     *   deleted. These bookings can only be cancelled (by updating the status to CANCELLED
     *   via the update endpoint). This restriction ensures data integrity for bookings
     *   that were paid through the mobile application.
     */
    public function destroy(Request $request, string $tenantId, $bookingId): JsonResponse
    {
        try {
            $decodedBookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $tenant           = $request->tenant;

            $this->deleteBookingService->delete($tenant, $decodedBookingId);

            return response()->json(['message' => 'Agendamento removido com sucesso']);
        } catch (HttpException $e) {
            // Log HTTP exceptions (validation errors, business logic errors)
            Log::warning('Erro ao remover agendamento', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'booking_id'  => $decodedBookingId ?? $bookingId ?? null,
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao remover agendamento', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'booking_id' => $decodedBookingId ?? $bookingId ?? null,
                'tenant_id'  => $request->tenant->id ?? null,
            ]);
            return response()->json(['message' => 'Erro ao remover agendamento'], 400);
        }
    }

    /**
     * Get bookings filtered by presence status
     *
     * Returns bookings filtered by presence status:
     * - all: returns all bookings regardless of presence status
     * - pending: present is null (not yet marked/confirmed)
     * - present: present is true (client was present)
     * - not-present: present is false/0 (client was not present)
     *
     * Presence field mapping:
     * - null = pending (not yet marked)
     * - true = present (was present)
     * - false/0 = not-present (not present)
     *
     * Get bookings filtered by presence status
     *
     * @queryParam presence_status string required Filter by presence status (all, pending, present, not-present). Example: "pending"
     * @queryParam search string Search by client name or court name. Example: "John"
     * @queryParam court_id string Filter by court (hashed ID). Example: "Xy7z..."
     * @queryParam status string Filter by booking status (confirmed, pending, cancelled). Example: "confirmed"
     * @queryParam payment_status string Filter by payment status (paid, pending). Example: "paid"
     */
    public function presence(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;
            $query  = Booking::forTenant($tenant->id)->with(['user', 'court', 'currency']);

            // Presence status filter - REQUIRED for this endpoint
            // null = pending (not yet marked)
            // true = present (was present)
            // false/0 = not-present (not present)
            if (! $request->has('presence_status')) {
                abort(400, 'O parâmetro presence_status é obrigatório. Valores aceites: all, pending, present, not-present');
            }

            $presenceStatus = strtolower($request->presence_status);
            if (! in_array($presenceStatus, ['all', 'pending', 'present', 'not-present'])) {
                abort(400, 'O valor do parâmetro presence_status é inválido. Valores aceites: all, pending, present, not-present');
            }

            if ($presenceStatus === 'all') {
                // Return all bookings regardless of presence status (no filter applied)
                // Do nothing - query remains unfiltered by presence
            } elseif ($presenceStatus === 'pending') {
                // present is null = pending (not yet marked)
                $query->whereNull('present');
            } elseif ($presenceStatus === 'present') {
                // present is true = present (was present)
                $query->where('present', true);
            } elseif ($presenceStatus === 'not-present') {
                // present is false/0 = not-present (not present)
                $query->where('present', false);
            }

            // Court filter
            if ($request->has('court_id')) {
                $courtId = EasyHashAction::decode($request->court_id, 'court-id');
                $query->forCourt($courtId);
            }

            // Status filter
            if ($request->has('status')) {
                $status = strtolower($request->status);
                if (in_array($status, ['confirmed', 'pending', 'cancelled'])) {
                    $statusEnum = match ($status) {
                        'confirmed' => BookingStatusEnum::CONFIRMED,
                        'pending'   => BookingStatusEnum::PENDING,
                        'cancelled' => BookingStatusEnum::CANCELLED,
                    };
                    $query->where('status', $statusEnum);
                }
            }

            // Payment status filter
            if ($request->has('payment_status')) {
                $paymentStatus = strtolower($request->payment_status);
                if (in_array($paymentStatus, ['paid', 'pending'])) {
                    $paymentStatusEnum = match ($paymentStatus) {
                        'paid'    => PaymentStatusEnum::PAID,
                        'pending' => PaymentStatusEnum::PENDING,
                    };
                    $query->where('payment_status', $paymentStatusEnum);
                }
            }

            // Search functionality
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    // Search by client name
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('surname', 'LIKE', "%{$search}%");
                    })
                    // Search by court name
                        ->orWhereHas('court', function ($courtQuery) use ($search) {
                            $courtQuery->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            $bookings = $query->orderBy('start_date', 'desc')
                ->orderBy('start_time', 'desc')
                ->paginate(20);

            return BookingResource::collection($bookings);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao listar agendamentos por presença', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao listar agendamentos por presença', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'tenant_id' => $request->tenant->id ?? null,
                'filters'   => $request->all(),
            ]);
            abort(500, 'Erro inesperado ao listar agendamentos por presença. Por favor, tente novamente.');
        }
    }

    /**
     * Confirm presence for a booking
     *
     * Updates the presence status of a booking (e.g., user checked in).
     *
     * Restrictions:
     * - Can only mark presence if it's the day of the booking AND at least 1 hour before the booking time
     * - OR if the booking datetime has already passed (allows retroactive marking)
     * - Cannot mark presence for future bookings that are not on the same day
     */
    public function confirmPresence(Request $request, string $tenantId, $bookingId): BookingResource
    {
        try {
            $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $booking   = Booking::forTenant($request->tenant->id)->find($bookingId);

            if (! $booking) {
                abort(404, 'Agendamento não encontrado');
            }

            $request->validate([
                'present' => 'required|boolean',
            ]);

            // Validate presence timing restrictions
            $this->validatePresenceTiming($booking);

            $booking->update([
                'present' => $request->present,
            ]);

            return new BookingResource($booking);
        } catch (HttpException $e) {
            // Re-throw HTTP exceptions with their specific status codes and messages
            Log::warning('Erro ao confirmar presença', [
                'error'       => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'booking_id'  => $bookingId ?? null,
                'tenant_id'   => $request->tenant->id ?? null,
            ]);
            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Erro inesperado ao confirmar presença', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'booking_id' => $bookingId ?? null,
                'tenant_id'  => $request->tenant->id ?? null,
            ]);
            abort(500, 'Erro inesperado ao confirmar presença. Por favor, tente novamente.');
        }
    }

    /**
     * Validate that presence can be marked based on booking timing
     *
     * Rules:
     * - Can mark if it's the same day as booking AND at least 1 hour before booking time
     * - Can mark if booking datetime has already passed (retroactive)
     * - Cannot mark if booking is in the future and not on the same day
     *
     * @param Booking $booking
     * @return void
     * @throws HttpException
     */
    protected function validatePresenceTiming(Booking $booking): void
    {
        $timezoneService = app(\App\Services\TimezoneService::class);
        
        // Get current time in both UTC and User timezone
        $nowUtc = \Carbon\Carbon::now('UTC');
        $nowUserTz = \Carbon\Carbon::now($timezoneService->getContextTimezone());

        // Combine start_date and start_time to get the full booking datetime in UTC
        $startDate = $booking->start_date; // Already a Carbon instance
        $startTime = $booking->start_time; // Already a Carbon instance (but date part is today)

        // Combine the date from start_date with the time from start_time (UTC)
        $bookingDateTimeUtc = \Carbon\Carbon::parse(
            $startDate->format('Y-m-d') . ' ' . $startTime->format('H:i:s'),
            'UTC'
        );

        // Convert booking datetime to user timezone for display logic
        $bookingDateTimeUserTz = $bookingDateTimeUtc->copy()->setTimezone($timezoneService->getContextTimezone());

        // Check if booking has already passed (use UTC for accurate comparison)
        if ($nowUtc->greaterThanOrEqualTo($bookingDateTimeUtc)) {
            // Booking has passed - allow marking presence (retroactive)
            return;
        }

        // Booking is in the future - check if it's the same day IN USER'S TIMEZONE
        // This ensures the validation matches what the user sees in the UI
        $isSameDay = $nowUserTz->format('Y-m-d') === $bookingDateTimeUserTz->format('Y-m-d');

        if (! $isSameDay) {
            // Not the same day - cannot mark presence for future bookings
            abort(400, 'Não é possível marcar presença para agendamentos futuros. Apenas é permitido marcar presença no dia do agendamento (com pelo menos 1 hora de antecedência) ou após o horário do agendamento.');
        }

        // Same day - check if it's at least 1 hour before booking time (use UTC for accurate comparison)
        $oneHourBeforeUtc = $bookingDateTimeUtc->copy()->subHour();
        if ($nowUtc->greaterThan($oneHourBeforeUtc)) {
            // Less than 1 hour before - cannot mark presence yet
            abort(400, 'Só é possível marcar presença com pelo menos 1 hora de antecedência do horário do agendamento.');
        }

        // Valid: same day and at least 1 hour before booking time
    }
}
