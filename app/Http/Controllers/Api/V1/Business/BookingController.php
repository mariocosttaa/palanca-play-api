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
     * @response 201 {"data": {"id": "mGbnVK9ryOK1y4Y6XlQgJ", "court_id": "2pX9g4KPNdz6dojQYaw16", "user_id": "W39mX2xdzrz8a5lVQLRoE", "start_date": "2025-12-22", "end_date": "2025-12-22", "start_time": "09:00", "end_time": "10:00", "price": 0, "currency": "aoa", "is_pending": false, "is_cancelled": false, "is_paid": false, "paid_at_venue": false, "created_at": "2025-12-22T14:37:53.000000Z"}}
     *
     * @responseExample 400 {"message": "Este horário já está reservado (10:00 - 11:00)."}
     * @responseExample 400 {"message": "Horário solicitado está fora do horário de funcionamento da quadra (09:00 - 21:00)."}
     * @responseExample 400 {"message": "Quadra não possui horário de funcionamento configurado para esta data."}
     * @responseExample 400 {"message": "Quadra marcada como indisponível nesta data."}
     * @responseExample 400 {"message": "Horário conflita com uma pausa configurada (12:00 - 13:00)."}
     * @responseExample 400 {"message": "Cliente inválido ou não fornecido."}
     * @responseExample 400 {"message": "Quadra inválida."}
     * @responseExample 500 {"message": "Erro inesperado ao criar agendamento. Por favor, tente novamente."}
     */
    public function store(CreateBookingRequest $request): BookingResource
    {
        try {
            $tenant = $request->tenant;
            $data   = $request->validated();

            $booking = $this->createBookingService->create($tenant, $data);

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
     * Restrictions:
     * - Bookings that have been marked as present cannot be updated, cancelled, or deleted.
     *   Users must contact support for assistance with these bookings.
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

            $booking = $this->updateBookingService->update($tenant, $decodedBookingId, $data);

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
     * - pending: present is null (not yet marked)
     * - confirmed: present is true
     * - rejected/canceled: present is false
     *
     * @queryParam presence_status string Filter by presence status (pending, confirmed, rejected, canceled). Example: "pending"
     * @queryParam search string Search by client name or court name. Example: "John"
     * @queryParam court_id string Filter by court (hashed ID). Example: "Xy7z..."
     * @queryParam status string Filter by booking status (confirmed, pending, cancelled). Example: "confirmed"
     * @queryParam payment_status string Filter by payment status (paid, pending). Example: "paid"
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function presence(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        try {
            $tenant = $request->tenant;
            $query  = Booking::forTenant($tenant->id)->with(['user', 'court', 'currency']);

            // Presence status filter
            if ($request->has('presence_status')) {
                $presenceStatus = strtolower($request->presence_status);
                if (in_array($presenceStatus, ['pending', 'confirmed', 'rejected', 'canceled'])) {
                    if ($presenceStatus === 'pending') {
                        $query->whereNull('present');
                    } elseif ($presenceStatus === 'confirmed') {
                        $query->where('present', true);
                    } elseif (in_array($presenceStatus, ['rejected', 'canceled'])) {
                        $query->where('present', false);
                    }
                }
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

            return response()->json([
                'data' => BookingResource::collection($bookings)->resolve(),
            ]);
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
}