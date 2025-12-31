<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Enums\BookingApiContextEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\CreateMobileBookingRequest;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use App\Models\Court;
use App\Services\Booking\CancelBookingService;
use App\Services\Booking\CreateBookingService;
use App\Services\Booking\UpdateBookingService;
use App\Services\EmailService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @tags [API-MOBILE] Bookings
 */
class MobileBookingController extends Controller
{
    public function __construct(
        protected CreateBookingService $createBookingService,
        protected UpdateBookingService $updateBookingService,
        protected CancelBookingService $cancelBookingService,
        protected NotificationService $notificationService,
        protected EmailService $emailService,
    ) {
    }
    /**
     * List authenticated user's bookings
     * 
     * @queryParam status string Filter by status (upcoming, past, cancelled). Example: upcoming
     * @queryParam page int The page number. Example: 1
     * @queryParam per_page int The number of items per page. Example: 20
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Business\V1\Specific\BookingResource>
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.primaryImage', 'currency', 'court.tenant']);

            // Filter by status if provided
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'upcoming':
                        $query->where('start_date', '>=', now()->format('Y-m-d'))
                            ->where('status', '!=', BookingStatusEnum::CANCELLED);
                        break;
                    case 'past':
                        $query->where('start_date', '<', now()->format('Y-m-d'))
                            ->where('status', '!=', BookingStatusEnum::CANCELLED);
                        break;
                    case 'cancelled':
                        $query->where('status', BookingStatusEnum::CANCELLED);
                        break;
                }
            }

            $bookings = $query->latest('start_date')->paginate(20);

            return BookingResource::collection($bookings);

        } catch (\Exception $e) {
            \Log::error('Erro ao buscar agendamentos', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamentos'], 500);
        }
    }

    /**
     * Create a new booking
     * 
     * Allows creating a booking for multiple contiguous slots.
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BookingResource
     */
    public function store(CreateMobileBookingRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            
            // Get Court
            $court = Court::with('tenant')->findOrFail($data['court_id']);

            // Get slots from request
            $slots = $data['slots'];
            
            // Determine start and end times from slots
            $startTime = $slots[0]['start'];
            $endTime = $slots[count($slots) - 1]['end'];

            // Calculate price based on number of slots and court type pricing
            $pricePerInterval = $court->courtType->price_per_interval ?? 0;
            $totalPrice = $pricePerInterval * count($slots);

            // Prepare booking data for service
            $bookingData = [
                'court_id' => $court->id,
                'client_id' => $user->id,
                'start_date' => $data['start_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'price' => $totalPrice,
                'payment_method' => PaymentMethodEnum::FROM_APP,
                'payment_status' => PaymentStatusEnum::PENDING,
                // Status will be determined by service based on tenant auto_confirm_bookings setting
            ];

            // Create booking using service (status handled automatically based on tenant settings)
            $booking = $this->createBookingService->create(
                $court->tenant,
                $bookingData,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $notificationResult = $this->notificationService->createBookingNotification(
                    $booking,
                    'created',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'created');
            } catch (\Exception $emailException) {
                Log::error('Failed to send booking email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return BookingResource::make($booking->load(['court.courtType', 'court.primaryImage', 'currency']));

        } catch (HttpException $e) {
            Log::warning('Erro ao criar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao criar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao criar agendamento'], 500);
        }
    }

    /**
     * Update a booking
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BookingResource
     */
    public function update(\App\Http\Requests\Api\V1\Mobile\UpdateMobileBookingRequest $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            // Find booking and verify ownership
            $booking = Booking::where('user_id', $user->id)
                ->with('tenant')
                ->findOrFail($bookingId);

            $data = $request->validated();

            // Convert slots to start_time and end_time if provided
            if (isset($data['slots']) && is_array($data['slots']) && count($data['slots']) > 0) {
                $data['start_time'] = $data['slots'][0]['start'];
                $data['end_time'] = $data['slots'][count($data['slots']) - 1]['end'];
                unset($data['slots']);
            }

            // If court_id is provided, we need to recalculate price based on slots
            if (isset($data['court_id'])) {
                $court = Court::with('courtType')->find($data['court_id']);
                if ($court && isset($data['start_time']) && isset($data['end_time'])) {
                    // Calculate price based on time difference and court type pricing
                    $start = \Carbon\Carbon::parse($data['start_time']);
                    $end = \Carbon\Carbon::parse($data['end_time']);
                    $minutes = $start->diffInMinutes($end);
                    $intervals = ceil($minutes / ($court->courtType->interval_time_minutes ?? 60));
                    $pricePerInterval = $court->courtType->price_per_interval ?? 0;
                    $data['price'] = $pricePerInterval * $intervals;
                }
            }

            // Update booking using service
            $booking = $this->updateBookingService->update(
                $booking->tenant,
                $bookingId,
                $data,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $this->notificationService->createBookingNotification(
                    $booking,
                    'updated',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for updated booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'updated');
            } catch (\Exception $emailException) {
                Log::error('Failed to send update email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return BookingResource::make($booking->load(['court.courtType', 'court.primaryImage', 'currency']));

        } catch (HttpException $e) {
            Log::warning('Erro ao atualizar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao atualizar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao atualizar agendamento'], 500);
        }
    }

    /**
     * Get booking details
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BookingResource
     */
    public function show(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            $booking = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.images', 'court.tenant', 'currency'])
                ->findOrFail($bookingId);

            return BookingResource::make($booking);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamento'], 500);
        }
    }

    /**
     * Cancel a booking
     * 
     * @urlParam booking_id string required The HashID of the booking. Example: abc123
     * 
     * @return array{message: string}
     */
    public function destroy(Request $request, string $bookingIdHashId)
    {
        try {
            $user = $request->user();
            $bookingId = EasyHashAction::decode($bookingIdHashId, 'booking-id');
            
            // Find booking and verify ownership
            $booking = Booking::where('user_id', $user->id)
                ->findOrFail($bookingId);

            // Cancel booking using service
            $booking = $this->cancelBookingService->cancel(
                $booking->tenant,
                $bookingId,
                BookingApiContextEnum::MOBILE
            );

            // Create notifications (for user and business users)
            try {
                $this->notificationService->createBookingNotification(
                    $booking,
                    'cancelled',
                    BookingApiContextEnum::MOBILE
                );
            } catch (\Exception $notifException) {
                Log::error('Failed to create notification for cancelled booking', [
                    'booking_id' => $booking->id,
                    'error' => $notifException->getMessage()
                ]);
            }

            // Send email to user
            try {
                $this->emailService->sendBookingEmail($user, $booking, 'cancelled');
            } catch (\Exception $emailException) {
                Log::error('Failed to send cancellation email', [
                    'booking_id' => $booking->id,
                    'error' => $emailException->getMessage()
                ]);
            }

            return response()->json(['message' => 'Agendamento cancelado com sucesso']);

        } catch (HttpException $e) {
            Log::warning('Erro ao cancelar agendamento', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao cancelar agendamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erro ao cancelar agendamento'], 500);
        }
    }

    /**
     * Get user booking statistics
     * 
     * @return array{
     *   data: array{
     *     total_bookings: int,
     *     upcoming_bookings: int,
     *     past_bookings: int,
     *     cancelled_bookings: int,
     *     pending_bookings: int
     *   }
     * }
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get booking statistics
            $totalBookings = Booking::where('user_id', $user->id)->count();
            
            $upcomingBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->count();
            
            $pastBookings = Booking::where('user_id', $user->id)
                ->where('start_date', '<', now()->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->count();
            
            $cancelledBookings = Booking::where('user_id', $user->id)
                ->where('status', BookingStatusEnum::CANCELLED)
                ->count();
            
            $pendingBookings = Booking::where('user_id', $user->id)
                ->where('status', BookingStatusEnum::PENDING)
                ->count();

            return response()->json([
                'data' => [
                    'total_bookings' => $totalBookings,
                    'upcoming_bookings' => $upcomingBookings,
                    'past_bookings' => $pastBookings,
                    'cancelled_bookings' => $cancelledBookings,
                    'pending_bookings' => $pendingBookings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar estatísticas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar estatísticas'], 500);
        }
    }

    /**
     * Get user recent bookings
     * 
     * @queryParam per_page int The number of items per page. Example: 10
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Business\V1\Specific\BookingResource>
     */
    public function getRecentBookings(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get recent bookings with pagination (lazy load support)
            $perPage = $request->input('per_page', 10);
            $bookings = Booking::where('user_id', $user->id)
                ->with(['court.courtType', 'court.primaryImage', 'court.tenant', 'currency'])
                ->latest('created_at')
                ->paginate($perPage);

            return BookingResource::collection($bookings);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agendamentos recentes', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar agendamentos recentes'], 500);
        }
    }

    /**
     * Get next upcoming booking
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BookingResource|array{data: null}
     */
    public function getNextBooking(Request $request)
    {
        try {
            $user = $request->user();
            
            $nextBooking = Booking::where('user_id', $user->id)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->with(['court.courtType', 'court.primaryImage', 'court.tenant', 'currency'])
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->first();

            return $nextBooking ? BookingResource::make($nextBooking) : response()->json(['data' => null]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar próximo agendamento', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao buscar próximo agendamento'], 500);
        }
    }
}
