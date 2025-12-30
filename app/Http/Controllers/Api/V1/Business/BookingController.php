<?php
namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Actions\General\QrCodeAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Business\CreateBookingRequest;
use App\Http\Requests\Api\V1\Business\UpdateBookingRequest;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Manager\CurrencyModel;
use App\Models\User;
use App\Models\UserTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @tags [API-BUSINESS] Bookings
 */
class BookingController extends Controller
{
    /**
     * Get a list of bookings with optional filters
     *
     * @queryParam search string Search by client name or court name. Example: "John"
     * @queryParam date string Filter by specific date (Y-m-d). Example: "2024-12-19"
     * @queryParam start_date string Start date for range filter. Example: "2024-12-01"
     * @queryParam end_date string End date for range filter. Example: "2024-12-31"
     * @queryParam court_id string Filter by court (hashed ID). Example: "Xy7z..."
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $tenant = $request->tenant;
        $query  = Booking::forTenant($tenant->id)->with(['user', 'court', 'currency']);

        if ($request->has('date')) {
            $query->onDate($request->date);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        }

        if ($request->has('court_id')) {
            $courtId = EasyHashAction::decode($request->court_id, 'court-id');
            $query->forCourt($courtId);
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
            $this->beginTransactionSafe();

            $tenant = $request->tenant;
            $data   = $request->validated();

            // Handle Client
            $clientId = $data['client_id'];

            // Get Court
            $court = Court::find($data['court_id']);
            if (! $court || $court->tenant_id !== $tenant->id) {
                abort(400, 'Quadra inválida.');
            }

            // Check availability and get specific error if not available
            $availabilityError = $court->checkAvailability($data['start_date'], $data['start_time'], $data['end_time'], $clientId);
            if ($availabilityError) {
                abort(400, $availabilityError);
            }

            // Prepare Booking Data
            $bookingData = [
                'tenant_id'      => $tenant->id,
                'court_id'       => $court->id,
                'user_id'        => $clientId,
                'currency_id'    => CurrencyModel::where('code', $tenant->currency)->first()?->id ?? 1,
                'start_date'     => $data['start_date'],
                'end_date'       => $data['start_date'], // Single day booking for now
                'start_time'     => $data['start_time'],
                'end_time'       => $data['end_time'],
                'price'          => $data['price'] ?? 0,
                'status'         => $data['status'] ?? BookingStatusEnum::CONFIRMED,
                'payment_status' => $data['payment_status'] ?? PaymentStatusEnum::PENDING,
                'payment_method' => $data['payment_method'] ?? null,
            ];

            $booking = Booking::create($bookingData);

            // Link user to tenant if not already linked
            UserTenant::firstOrCreate([
                'user_id'   => $clientId,
                'tenant_id' => $tenant->id,
            ]);

            // Generate QR code with hashed booking ID
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
                    'error'      => $qrException->getMessage(),
                ]);
            }

            $this->commitSafe();

            $booking->load('court');

            return new BookingResource($booking);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Let HTTP exceptions (abort calls) bubble up with their specific messages
            $this->rollBackSafe();
            throw $e;
        } catch (\Exception $e) {
            // Only catch unexpected exceptions
            $this->rollBackSafe();
            Log::error('Erro inesperado ao criar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
        $booking   = Booking::forTenant($request->tenant->id)->with(['user', 'court'])->find($bookingId);

        if (! $booking) {
            abort(404, 'Agendamento não encontrado');
        }

        return new BookingResource($booking);
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

            $booking = Booking::forTenant($request->tenant->id)->find($decodedBookingId);

            if (! $booking) {
                abort(404, 'Agendamento não encontrado');
            }

            // Prevent any update if booking is marked as present
            if ($booking->present === true) {
                abort(400, 'Não é possível modificar um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.');
            }

            $data = $request->validated();

            // Check availability if dates/times/court are changing
            if (isset($data['court_id']) || isset($data['start_date']) || isset($data['start_time']) || isset($data['end_time'])) {
                // Get current values if not provided in update
                $startDate = $data['start_date'] ?? \Carbon\Carbon::parse($booking->start_date)->format('Y-m-d');
                $startTime = $data['start_time'] ?? \Carbon\Carbon::parse($booking->start_time)->format('H:i');
                $endTime   = $data['end_time'] ?? \Carbon\Carbon::parse($booking->end_time)->format('H:i');

                // Get court - use new court if provided, otherwise use current court
                if (isset($data['court_id'])) {
                    $court = Court::find($data['court_id']);
                    if (! $court || $court->tenant_id !== $booking->tenant_id) {
                        abort(400, 'Quadra inválida.');
                    }
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
                    abort(400, $availabilityError);
                }
            }

            $booking->update($data);

            $booking->load('court');

            return new BookingResource($booking);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Let HTTP exceptions (abort calls) bubble up with their specific messages
            throw $e;
        } catch (\Exception $e) {
            // Only catch unexpected exceptions
            Log::error('Erro ao atualizar agendamento', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao atualizar agendamento');
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
            $bookingId = EasyHashAction::decode($bookingId, 'booking-id');
            $booking   = Booking::forTenant($request->tenant->id)->find($bookingId);

            if (! $booking) {
                return response()->json(['message' => 'Agendamento não encontrado'], 404);
            }

            // Prevent deletion if booking is marked as present
            if ($booking->present === true) {
                return response()->json([
                    'message' => 'Não é possível excluir um agendamento onde o cliente já esteve presente. Por favor, entre em contato com o suporte para assistência.',
                ], 400);
            }

            // Prevent deletion if booking was paid from app (can only cancel, not delete)
            if ($booking->payment_method !== null && $booking->payment_method === PaymentMethodEnum::FROM_APP) {
                return response()->json([
                    'message' => 'Não é possível excluir um agendamento que foi pago pelo aplicativo. Você pode cancelar o agendamento alterando o status para cancelado.',
                ], 400);
            }

            $booking->delete();

            return response()->json(['message' => 'Agendamento removido com sucesso']);

        } catch (\Exception $e) {
            Log::error('Erro ao remover agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao remover agendamento'], 400);
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

        } catch (\Exception $e) {
            Log::error('Erro ao confirmar presença', ['error' => $e->getMessage()]);
            abort(400, 'Erro ao confirmar presença');
        }
    }
}
